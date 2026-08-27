/** Fills the native Mautic filter builder on the new-segment page only. */
(function () {
  'use strict';

  var runtimeConfig = window.MauticAiEmailSectionsConfig || {};

  function t(key, fallback) {
    if (window.Mautic && typeof window.Mautic.translate === 'function') {
      var translated = window.Mautic.translate(key);
      if (translated && translated !== key) {
        return translated;
      }
    }
    return fallback;
  }

  function config() {
    return runtimeConfig;
  }

  function catalogFromForm() {
    return Array.prototype.map.call(
      document.querySelectorAll('#available_segment_filters option[value]'),
      function (option) {
        if (!option.value) {
          return null;
        }

        var parsed = {};
        try {
          parsed = JSON.parse(option.getAttribute('data-field-operators') || '{}');
        } catch (ignore) {
          parsed = {};
        }

        return {
          object: option.getAttribute('data-field-object') || 'lead',
          field: option.value,
          label: (option.textContent || '').trim(),
          type: option.getAttribute('data-field-type') || 'text',
          operators: Object.keys(parsed).map(function (label) {
            return { label: label, value: parsed[label] };
          }),
        };
      }
    ).filter(Boolean);
  }

  function requestPlan(prompt, catalog) {
    return fetch(config().segmentEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.mauticAjaxCsrf || '',
      },
      body: JSON.stringify({ prompt: prompt, catalog: catalog }),
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) {
          var message = data.message || t('mautic.aiemailsections.segment.error.generic', 'The filters could not be prepared.');
          if (data.details) {
            message += ' ' + data.details;
          }
          throw new Error(message);
        }
        return data;
      });
    });
  }

  function findFieldOption(object, field) {
    var options = document.querySelectorAll('#available_segment_filters option[value]');
    for (var i = 0; i < options.length; i++) {
      if (options[i].value === field && options[i].getAttribute('data-field-object') === object) {
        return options[i];
      }
    }
    return null;
  }

  function filterIndex(panel) {
    var match = /leadlist_filters_(\d+)/.exec(panel.id || '');
    return match ? parseInt(match[1], 10) : -1;
  }

  function loadProperties(index, condition) {
    return new Promise(function (resolve, reject) {
      var selector = '#leadlist_filters_' + index;
      var timeout = window.setTimeout(function () {
        reject(new Error(t('mautic.aiemailsections.segment.error.form', 'Mautic did not load the filter controls.')));
      }, 10000);

      window.Mautic.loadFilterForm(
        index,
        condition.object,
        condition.field,
        condition.operator,
        function (html) {
          window.clearTimeout(timeout);
          window.mQuery(selector + '_properties').html(html);
          window.Mautic.triggerOnPropertiesFormLoadedEvent(selector, null);
          resolve(selector);
        }
      );
    });
  }

  function addNativeFilter(field, object) {
    return new Promise(function (resolve, reject) {
      var originalLoad = window.Mautic.loadFilterForm;
      if (typeof originalLoad !== 'function') {
        reject(new Error(t('mautic.aiemailsections.segment.error.form', 'Mautic did not expose the filter loader.')));
        return;
      }

      var timeout = window.setTimeout(function () {
        reject(new Error(t('mautic.aiemailsections.segment.error.form', 'Mautic did not load the filter controls.')));
      }, 10000);

      window.Mautic.loadFilterForm = function () {
        var args = Array.prototype.slice.call(arguments);
        var originalCallback = args[4];
        args[4] = function (html) {
          originalCallback(html);
          window.clearTimeout(timeout);
          resolve();
        };
        return originalLoad.apply(window.Mautic, args);
      };

      try {
        window.Mautic.addLeadListFilter(field, object);
      } catch (error) {
        window.clearTimeout(timeout);
        reject(error);
      } finally {
        window.Mautic.loadFilterForm = originalLoad;
      }
    });
  }

  function equalText(left, right) {
    return String(left).trim().toLocaleLowerCase() === String(right).trim().toLocaleLowerCase();
  }

  function resolveChoice(select, requested) {
    var options = Array.prototype.slice.call(select.options || []);
    var exact = options.find(function (option) { return String(option.value) === String(requested); });
    var byLabel = exact || options.find(function (option) { return equalText(option.textContent, requested); });

    if (!byLabel) {
      throw new Error(
        t('mautic.aiemailsections.segment.error.choice', 'A requested value is not available in this Mautic field:') + ' ' + requested
      );
    }
    return byLabel.value;
  }

  function lookupValue(field, requested) {
    return new Promise(function (resolve, reject) {
      window.mQuery.ajax({
        url: window.mauticAjaxUrl,
        type: 'GET',
        dataType: 'json',
        data: { action: 'lead:fieldList', field: field, filter: requested },
        success: function (response) {
          var rows = Array.isArray(response) ? response : Object.keys(response || {}).map(function (key) { return response[key]; });
          var matches = rows.filter(function (row) {
            return row && row.id !== undefined && (equalText(row.value, requested) || equalText(row.name, requested));
          });
          if (matches.length !== 1) {
            reject(new Error(t('mautic.aiemailsections.segment.error.lookup', 'The lookup value is missing or ambiguous:') + ' ' + requested));
            return;
          }
          resolve({ id: matches[0].id, display: matches[0].value || matches[0].name || requested });
        },
        error: function () {
          reject(new Error(t('mautic.aiemailsections.segment.error.lookup', 'The lookup value could not be resolved:') + ' ' + requested));
        },
      });
    });
  }

  function setConditionValue(index, condition) {
    var base = '#leadlist_filters_' + index + '_properties_';
    var filter = document.querySelector(base + 'filter');
    var display = document.querySelector(base + 'display');
    var value = condition.value;

    if (value === null || value === undefined) {
      return Promise.resolve();
    }

    if (display && filter && filter.type === 'hidden' && !/^\d+$/.test(String(value))) {
      return lookupValue(condition.field, condition.display || value).then(function (resolved) {
        filter.value = resolved.id;
        display.value = resolved.display;
      });
    }

    if (!filter) {
      throw new Error(t('mautic.aiemailsections.segment.error.form', 'Mautic did not expose a value control for one condition.'));
    }

    if (filter.tagName === 'SELECT') {
      var requested = Array.isArray(value) ? value : [value];
      var resolvedValues = requested.map(function (item) { return resolveChoice(filter, item); });
      window.mQuery(filter).val(filter.multiple ? resolvedValues : resolvedValues[0]).trigger('chosen:updated').trigger('change');
      return Promise.resolve();
    }

    filter.value = Array.isArray(value) ? value.join('|') : value;
    filter.dispatchEvent(new Event('change', { bubbles: true }));
    return Promise.resolve();
  }

  function addCondition(condition, glue) {
    var option = findFieldOption(condition.object, condition.field);
    if (!option) {
      return Promise.reject(new Error(t('mautic.aiemailsections.segment.error.field', 'A requested filter is not available in this Mautic.')));
    }

    var initialLoad = addNativeFilter(condition.field, condition.object);
    var panels = document.querySelectorAll('#leadlist_filters .segment-filter');
    var panel = panels[panels.length - 1];
    var index = panel ? filterIndex(panel) : -1;
    if (index < 0) {
      return Promise.reject(new Error(t('mautic.aiemailsections.segment.error.form', 'Mautic did not add the filter.')));
    }

    var glueSelect = document.querySelector('#leadlist_filters_' + index + '_glue');
    var operatorSelect = document.querySelector('#leadlist_filters_' + index + '_operator');
    if (glueSelect) {
      glueSelect.value = glue;
      window.Mautic.updateFilterPositioning(glueSelect);
    }
    if (!operatorSelect || !Array.prototype.some.call(operatorSelect.options, function (item) { return item.value === condition.operator; })) {
      panel.remove();
      return Promise.reject(new Error(t('mautic.aiemailsections.segment.error.operator', 'A requested operator is not available.')));
    }
    return initialLoad.then(function () {
      operatorSelect.value = condition.operator;
      return loadProperties(index, condition);
    }).then(function () {
      return setConditionValue(index, condition);
    }).then(function () {
      return panel;
    });
  }

  function applyPlan(plan) {
    if (document.querySelectorAll('#leadlist_filters .segment-filter').length) {
      return Promise.reject(new Error(t('mautic.aiemailsections.segment.error.not_empty', 'Remove the existing filters before using the assistant.')));
    }

    var chain = Promise.resolve();
    (plan.groups || []).forEach(function (group, groupIndex) {
      (group.conditions || []).forEach(function (condition, conditionIndex) {
        chain = chain.then(function () {
          var glue = groupIndex > 0 && conditionIndex === 0 ? 'or' : 'and';
          return addCondition(condition, glue);
        });
      });
    });

    return chain.then(function () {
      window.Mautic.reorderSegmentFilters();
    }).catch(function (error) {
      Array.prototype.forEach.call(document.querySelectorAll('#leadlist_filters .segment-filter'), function (panel) {
        if (panel.parentNode) {
          panel.parentNode.removeChild(panel);
        }
      });
      window.Mautic.reorderSegmentFilters();
      throw error;
    });
  }

  function init() {
    if (!/^\/s\/segments\/new\/?$/.test(window.location.pathname) || document.getElementById('ai-segment-assistant')) {
      return;
    }

    var filters = document.getElementById('filters');
    var anchor = filters && filters.querySelector('.alert-info');
    if (!filters || !anchor || !config().segmentEndpoint) {
      return;
    }

    var panel = document.createElement('section');
    panel.id = 'ai-segment-assistant';
    panel.className = 'ai-segment-assistant';
    panel.innerHTML =
      '<div class="ai-segment-assistant__title">' + t('mautic.aiemailsections.segment.title', 'AI segment assistant') + '</div>' +
      '<textarea class="form-control ai-segment-assistant__prompt" rows="3"></textarea>' +
      '<div class="ai-segment-assistant__actions"><button type="button" class="btn btn-primary ai-segment-assistant__run">' +
      t('mautic.aiemailsections.segment.generate', 'Prepare filters') + '</button></div>' +
      '<div class="ai-segment-assistant__status" role="status"></div>';
    anchor.parentNode.insertBefore(panel, anchor);

    var prompt = panel.querySelector('.ai-segment-assistant__prompt');
    var button = panel.querySelector('.ai-segment-assistant__run');
    var status = panel.querySelector('.ai-segment-assistant__status');
    prompt.placeholder = t('mautic.aiemailsections.segment.placeholder', 'Describe who should be included in the segment.');

    function setStatus(message, kind) {
      status.textContent = message || '';
      status.className = 'ai-segment-assistant__status' + (kind ? ' is-' + kind : '');
    }

    button.addEventListener('click', function () {
      var text = prompt.value.trim();
      if (!text) {
        setStatus(t('mautic.aiemailsections.segment.error.empty', 'Describe the segment conditions.'), 'error');
        return;
      }

      var catalog = catalogFromForm();
      button.disabled = true;
      setStatus(t('mautic.aiemailsections.segment.loading', 'Preparing and validating filters...'), 'loading');

      requestPlan(text, catalog)
        .then(applyPlan)
        .then(function () {
          button.disabled = false;
          setStatus(t('mautic.aiemailsections.segment.ready', 'Filters are ready. Review them before saving.'), 'success');
        })
        .catch(function (error) {
          button.disabled = false;
          setStatus(error.message, 'error');
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Mautic replaces admin content without reloading the document. Its supported
  // completion hook is the jQuery ajaxComplete event on the document.
  if (typeof window.mQuery === 'function') {
    var mauticDocument = window.mQuery(document);
    if (mauticDocument && typeof mauticDocument.ajaxComplete === 'function') {
      mauticDocument.ajaxComplete(init);
    }
  }
})();
