/** Adds and removes unlimited AI profile rows in Mautic's integration form. */
(function () {
  'use strict';

  function t(key, fallback) {
    if (window.Mautic && typeof window.Mautic.translate === 'function') {
      var translated = window.Mautic.translate(key);
      if (translated && translated !== key) {
        return translated;
      }
    }
    return fallback;
  }

  function markProfileFields(row) {
    var fields = {
      name: 'half',
      provider: 'half',
      base_url: 'wide',
      model: 'half',
      brand: 'wide',
      tokens_enabled: 'compact',
      tokens_excluded: 'wide',
      temperature: 'compact',
      max_tokens: 'compact',
      placeholder_image: 'wide',
      rate_limit: 'compact',
      timeout: 'compact',
      theme: 'half'
    };

    Object.keys(fields).forEach(function (key) {
      var control = row.querySelector('[name$="[' + key + ']"]');
      if (!control) {
        return;
      }

      var group = control.closest('.form-group');
      if (!group) {
        return;
      }

      group.classList.add('ai-profile-field--' + key);
      group.classList.add('ai-profile-field--' + fields[key]);
    });
  }

  function activateProfileControls(row) {
    if (!window.Mautic || typeof window.Mautic.activateChosenSelect !== 'function') {
      return;
    }
    row.querySelectorAll('select').forEach(function (select) {
      window.Mautic.activateChosenSelect(select);
    });
  }

  function pluginEnabled(modal) {
    var yes = modal.querySelector('#integration_config_isPublished_1');
    if (yes) {
      return !!yes.checked;
    }
    var toggle = modal.querySelector('#integration_config_isPublished_label');
    return !!toggle && toggle.getAttribute('aria-checked') === 'true';
  }

  function removeRuntimeUi() {
    document.querySelectorAll('.gjs-assistant, .ai-segment-assistant').forEach(function (element) {
      element.remove();
    });
  }

  function syncPluginState(modal) {
    var enabled = pluginEnabled(modal);
    var tab = modal.querySelector('#features-tab');
    var link = tab ? tab.querySelector('a') : null;
    var panel = modal.querySelector('#features-container');

    window.MauticAiEmailSectionsDisabled = !enabled;

    if (tab) {
      tab.classList.toggle('ai-tab-disabled', !enabled);
      tab.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }
    if (link) {
      if (enabled) {
        link.removeAttribute('tabindex');
        link.removeAttribute('aria-disabled');
      } else {
        link.setAttribute('tabindex', '-1');
        link.setAttribute('aria-disabled', 'true');
      }
    }
    if (panel) {
      panel.setAttribute('aria-disabled', enabled ? 'false' : 'true');
      if (enabled) {
        panel.removeAttribute('inert');
      } else {
        panel.setAttribute('inert', '');
      }
    }
    if (!enabled) {
      removeRuntimeUi();
    }
  }

  function wirePluginState(modal) {
    var details = modal.querySelector('#details-container');
    var activeLabel = modal.querySelector('#details-container > .row.form-group .control-label');
    if (activeLabel) {
      activeLabel.textContent = t('mautic.aiemailsections.config.active', 'Active');
    }
    if (details && !details.querySelector('.ai-email-sections-copyright')) {
      var copyright = document.createElement('div');
      copyright.className = 'ai-email-sections-copyright';
      copyright.appendChild(document.createTextNode('Copyright 2026 '));
      var link = document.createElement('a');
      link.href = 'https://sales-snap.com';
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = 'Sales Snap';
      copyright.appendChild(link);
      copyright.appendChild(document.createTextNode('. All Rights Reserved.'));
      details.appendChild(copyright);
    }
    syncPluginState(modal);
    if (modal.getAttribute('data-ai-plugin-state-ready') === '1') {
      return;
    }
    modal.setAttribute('data-ai-plugin-state-ready', '1');

    var toggle = modal.querySelector('#integration_config_isPublished_label');
    if (toggle && typeof MutationObserver === 'function') {
      new MutationObserver(function () {
        syncPluginState(modal);
      }).observe(toggle, { attributes: true, attributeFilter: ['aria-checked'] });
    }

    modal.querySelectorAll('[name="integration_config[isPublished]"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        window.setTimeout(function () {
          syncPluginState(modal);
        }, 0);
      });
    });

    var featuresLink = modal.querySelector('#features-tab a');
    if (featuresLink) {
      featuresLink.addEventListener('click', function (event) {
        if (!pluginEnabled(modal)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }, true);
    }
  }

  function init() {
    document.querySelectorAll('#IntegrationEditModal').forEach(function (modal) {
      if (!modal.querySelector('.ai-profile-collection')) {
        modal.classList.remove('ai-email-sections-modal');
      }
    });

    document.querySelectorAll('.ai-profile-collection').forEach(function (collection) {
      var modal = collection.closest('#IntegrationEditModal');
      if (modal) {
        modal.classList.add('ai-email-sections-modal');
        wirePluginState(modal);
      }

      if (collection.getAttribute('data-ai-profile-ready') === '1') {
        return;
      }

      var prototype = collection.getAttribute('data-prototype');
      if (!prototype) {
        return;
      }

      collection.setAttribute('data-ai-profile-ready', '1');
      var formGroup = collection.closest('.form-group');
      if (formGroup) {
        formGroup.classList.add('ai-profile-form-group');
      }
      var counter = collection.querySelectorAll('.ai-profile-row').length;
      var toolbar = document.createElement('div');
      toolbar.className = 'ai-profile-toolbar';
      var selectorWrap = document.createElement('div');
      selectorWrap.className = 'ai-profile-selector-wrap';
      var selectorLabel = document.createElement('label');
      selectorLabel.className = 'control-label';
      selectorLabel.textContent = t('mautic.aiemailsections.profile.select', 'Profile');
      var selector = document.createElement('select');
      selector.className = 'form-control ai-profile-selector';
      selectorWrap.appendChild(selectorLabel);
      selectorWrap.appendChild(selector);

      var create = document.createElement('button');
      create.type = 'button';
      create.className = 'btn btn-secondary ai-profile-create';
      create.textContent = t('mautic.aiemailsections.profile.new', 'New profile');
      toolbar.appendChild(selectorWrap);
      toolbar.appendChild(create);
      collection.parentNode.insertBefore(toolbar, collection);

      var actions = document.createElement('div');
      actions.className = 'ai-profile-actions';
      var save = document.createElement('button');
      save.type = 'button';
      save.className = 'btn btn-primary ai-profile-save';
      save.textContent = t('mautic.aiemailsections.profile.save', 'Save profile');
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-danger ai-profile-remove';
      remove.textContent = t('mautic.aiemailsections.profile.remove', 'Remove profile');
      var status = document.createElement('div');
      status.className = 'ai-profile-status';
      status.setAttribute('aria-live', 'polite');
      actions.appendChild(save);
      actions.appendChild(remove);
      actions.appendChild(status);
      collection.parentNode.insertBefore(actions, collection.nextSibling);

      function rows() {
        return Array.prototype.slice.call(collection.querySelectorAll('.ai-profile-row'));
      }

      function itemFor(row) {
        var item = row;
        while (item && item.parentElement !== collection) {
          item = item.parentElement;
        }
        return item || row;
      }

      function valueFor(row, field) {
        var control = row.querySelector('[name$="[' + field + ']"]');
        return control ? String(control.value || '').trim() : '';
      }

      function profileId(row) {
        return valueFor(row, 'id');
      }

      function profileName(row) {
        return valueFor(row, 'name') || t('mautic.aiemailsections.profile.untitled', 'New profile');
      }

      function attachProfileKey(row) {
        if (!modal || row.querySelector('.ai-profile-field--api_key')) {
          return;
        }
        var id = profileId(row);
        if (!id) {
          return;
        }

        var input = Array.prototype.find.call(
          modal.querySelectorAll('#integration_config_apiKeys_profiles input[type="password"]'),
          function (candidate) {
            return candidate.name.endsWith('[profiles][' + id + ']') ||
              candidate.name.endsWith('[profiles][' + id + '][api_key]');
          }
        );
        if (!input) {
          input = document.createElement('input');
          input.type = 'password';
          input.autocomplete = 'off';
        }
        input.name = 'integration_config[apiKeys][profiles][' + id + ']';
        input.id = 'integration_config_apiKeys_profiles_' + id.replace(/[^a-zA-Z0-9_-]/g, '_');
        input.classList.add('form-control');

        var group = document.createElement('div');
        group.className = 'row form-group ai-profile-field--api_key ai-profile-field--wide';
        var labelColumn = document.createElement('div');
        labelColumn.className = 'col-md-4';
        var label = document.createElement('label');
        label.className = 'control-label';
        label.htmlFor = input.id;
        label.textContent = t('mautic.aiemailsections.profile.api_key', 'API key');
        labelColumn.appendChild(label);
        var controlColumn = document.createElement('div');
        controlColumn.className = 'col-md-8';
        var control = document.createElement('div');
        control.appendChild(input);
        var help = document.createElement('div');
        help.className = 'help-block';
        help.textContent = t(
          'mautic.aiemailsections.profile.api_key.help',
          'Encrypted by Mautic. Leave empty if this provider does not require authentication.'
        );
        control.appendChild(help);
        controlColumn.appendChild(control);
        group.appendChild(labelColumn);
        group.appendChild(controlColumn);
        row.appendChild(group);
      }

      if (modal) {
        var keySource = modal.querySelector('#integration_config_apiKeys');
        var keySourceRow = keySource ? keySource.closest('#details-container > .row.form-group') : null;
        if (keySourceRow) {
          keySourceRow.classList.add('ai-profile-key-source');
        }
      }

      function syncDefaultProfile(row) {
        var defaultSelect = document.querySelector('#IntegrationEditModal [name$="[default_profile]"]');
        if (!defaultSelect) {
          return;
        }

        var id = profileId(row);
        var option = Array.prototype.find.call(defaultSelect.options, function (candidate) {
          return candidate.value === id;
        });
        if (!option) {
          option = document.createElement('option');
          option.value = id;
          defaultSelect.appendChild(option);
        }
        option.textContent = profileName(row);

        if (typeof window.mQuery === 'function') {
          var chosen = window.mQuery(defaultSelect);
          if (chosen && typeof chosen.trigger === 'function') {
            chosen.trigger('chosen:updated');
          }
        }
      }

      function syncSelector(row) {
        var id = profileId(row);
        var option = Array.prototype.find.call(selector.options, function (candidate) {
          return candidate.value === id;
        });
        if (!option) {
          option = document.createElement('option');
          option.value = id;
          selector.appendChild(option);
        }
        option.textContent = profileName(row);
      }

      function activeRow() {
        return rows().find(function (row) {
          return itemFor(row).classList.contains('ai-profile-item--active');
        }) || null;
      }

      function activate(row) {
        rows().forEach(function (candidate) {
          itemFor(candidate).classList.toggle('ai-profile-item--active', candidate === row);
        });
        if (row) {
          selector.value = profileId(row);
        }
        remove.disabled = rows().length <= 1;
        status.textContent = '';
      }

      rows().forEach(function (row) {
        markProfileFields(row);
        attachProfileKey(row);
        syncSelector(row);
      });
      activate(rows()[0] || null);

      selector.addEventListener('change', function () {
        var selected = rows().find(function (row) {
          return profileId(row) === selector.value;
        });
        if (selected) {
          activate(selected);
        }
      });

      create.addEventListener('click', function () {
        var index = counter++;
        var html = prototype.replace(/__name__/g, index);
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        var item = wrapper.firstElementChild;
        var row = item ? item.querySelector('.ai-profile-row') : null;
        if (!item || !row) {
          return;
        }

        var id = row.querySelector('[name$="[id]"]');
        if (id) {
          id.value = 'profile-' + Date.now() + '-' + index;
        }
        collection.appendChild(item);
        markProfileFields(row);
        attachProfileKey(row);
        activateProfileControls(row);
        syncSelector(row);
        activate(row);

        var name = row.querySelector('[name$="[name]"]');
        if (name) {
          name.focus();
        }
      });

      save.addEventListener('click', function () {
        var row = activeRow();
        if (!row) {
          return;
        }
        var name = row.querySelector('[name$="[name]"]');
        if (name && !String(name.value || '').trim()) {
          if (typeof name.reportValidity === 'function') {
            name.reportValidity();
          }
          return;
        }

        syncSelector(row);
        syncDefaultProfile(row);
        selector.value = profileId(row);
        var apply = modal ? modal.querySelector('#integration_config_buttons_apply') : null;
        if (apply) {
          status.textContent = t('mautic.aiemailsections.profile.saving', 'Saving profile...');
          apply.click();
        } else {
          status.textContent = t(
            'mautic.aiemailsections.profile.saved',
            'Profile saved in the form. Use Save below to persist all settings.'
          );
        }
      });

      remove.addEventListener('click', function () {
        var row = activeRow();
        if (!row || rows().length <= 1) {
          return;
        }
        var id = profileId(row);
        var allRows = rows();
        var index = allRows.indexOf(row);
        itemFor(row).remove();

        var selectorOption = Array.prototype.find.call(selector.options, function (candidate) {
          return candidate.value === id;
        });
        if (selectorOption) {
          selectorOption.remove();
        }

        var defaultSelect = document.querySelector('#IntegrationEditModal [name$="[default_profile]"]');
        if (defaultSelect) {
          var defaultOption = Array.prototype.find.call(defaultSelect.options, function (candidate) {
            return candidate.value === id;
          });
          if (defaultOption) {
            defaultOption.remove();
          }
          if (!defaultSelect.value && defaultSelect.options.length) {
            defaultSelect.value = defaultSelect.options[0].value;
          }
          if (typeof window.mQuery === 'function') {
            var chosen = window.mQuery(defaultSelect);
            if (chosen && typeof chosen.trigger === 'function') {
              chosen.trigger('chosen:updated');
            }
          }
        }

        var remaining = rows();
        activate(remaining[Math.min(index, remaining.length - 1)] || null);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  if (typeof window.mQuery === 'function') {
    var mauticDocument = window.mQuery(document);
    if (mauticDocument && typeof mauticDocument.ajaxComplete === 'function') {
      mauticDocument.ajaxComplete(init);
    }
  }
})();
