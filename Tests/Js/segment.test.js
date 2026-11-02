const fs = require('fs');
const path = require('path');

const SOURCE = path.join(__dirname, '../../Assets/src/segment.js');

function fieldOption(object, field, label, type, operators) {
  const option = document.createElement('option');
  option.value = field;
  option.textContent = label;
  option.setAttribute('data-field-object', object);
  option.setAttribute('data-field-type', type);
  option.setAttribute('data-field-operators', JSON.stringify(operators));
  return option;
}

function loadPage(pathname = '/s/segments/new') {
  window.history.replaceState({}, '', pathname);
  document.body.innerHTML = '<div id="filters"><div class="alert-info"></div><select id="available_segment_filters"><option value=""></option></select><div id="leadlist_filters"></div></div>';
  document.getElementById('available_segment_filters').appendChild(
    fieldOption('lead', 'device_os', 'Device OS', 'device_os', { includes: 'in' })
  );
  window.MauticAiEmailSectionsConfig = {
    segmentEndpoint: '/s/ai-email-sections/segment-plan',
    quickStarts: [
      {
        id: 'recent_visitors',
        label: 'mautic.aiemailsections.segment.quick_start.recent_visitors',
        promptKey: 'mautic.aiemailsections.segment.quick_start.recent_visitors.prompt',
        prompt: 'Contacts who visited the website in the last 30 days',
      },
      {
        id: 'inactive_contacts',
        label: 'mautic.aiemailsections.segment.quick_start.inactive_contacts',
        promptKey: 'mautic.aiemailsections.segment.quick_start.inactive_contacts.prompt',
        prompt: 'Contacts who have not opened an email in the last 90 days',
      },
    ],
  };
  window.MauticAiEmailSectionsDisabled = false;
  window.mauticAjaxCsrf = 'token';
  window.Mautic = { translate: (key) => key };
  window.__aiSegmentAjaxComplete = null;
  window.mQuery = jest.fn(() => ({
    ajaxComplete: (callback) => { window.__aiSegmentAjaxComplete = callback; },
  }));
  window.eval(fs.readFileSync(SOURCE, 'utf8'));
}

describe('new segment assistant', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
  });

  test('is rendered only on the new-segment route', () => {
    loadPage('/s/segments/new');
    expect(document.getElementById('ai-segment-assistant')).not.toBeNull();

    loadPage('/s/segments/edit/12');
    expect(document.getElementById('ai-segment-assistant')).toBeNull();
  });

  test('is not rendered while the integration is disabled', () => {
    loadPage('/s/segments/new');
    document.getElementById('ai-segment-assistant').remove();
    window.MauticAiEmailSectionsDisabled = true;
    window.__aiSegmentAjaxComplete();

    expect(document.getElementById('ai-segment-assistant')).toBeNull();
  });

  test('is rendered after Mautic replaces the page during internal navigation', () => {
    loadPage('/s/segments');
    expect(document.getElementById('ai-segment-assistant')).toBeNull();

    window.history.replaceState({}, '', '/s/segments/new');
    document.body.innerHTML = '<div id="filters"><div class="alert-info"></div><select id="available_segment_filters"><option value=""></option></select><div id="leadlist_filters"></div></div>';
    window.__aiSegmentAjaxComplete();

    expect(document.getElementById('ai-segment-assistant')).not.toBeNull();
  });

  test('uses a single ready-made prompt selector and apply button', () => {
    loadPage();

    const select = document.querySelector('.ai-segment-assistant__quick-start-select');
    const apply = document.querySelector('.ai-segment-assistant__quick-start-apply');
    expect(select.options).toHaveLength(3);
    expect(document.querySelectorAll('.ai-segment-assistant__quick-start')).toHaveLength(0);
    expect(apply.disabled).toBe(true);

    select.value = 'inactive_contacts';
    select.dispatchEvent(new Event('change'));
    apply.click();

    expect(apply.disabled).toBe(false);
    expect(document.querySelector('.ai-segment-assistant__prompt').value).toBe(
      'Contacts who have not opened an email in the last 90 days'
    );
  });

  test('sends the exact live field aliases and operators to the endpoint', async () => {
    const fetchMock = jest.fn().mockResolvedValue({
      ok: false,
      json: () => Promise.resolve({ message: 'stop after request' }),
    });
    window.fetch = fetchMock;
    loadPage();
    document.querySelector('.ai-segment-assistant__prompt').value = 'Android users';
    document.querySelector('.ai-segment-assistant__run').click();
    await Promise.resolve();
    await Promise.resolve();

    const payload = JSON.parse(fetchMock.mock.calls[0][1].body);
    expect(payload.catalog).toEqual([{
      object: 'lead',
      field: 'device_os',
      label: 'Device OS',
      type: 'device_os',
      operators: [{ label: 'includes', value: 'in' }],
    }]);
  });

  test('waits for the native filter request before applying the planned value', async () => {
    window.fetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        groups: [{ conditions: [{ object: 'behaviors', field: 'hit_url_date', operator: 'gte', value: '-10 days' }] }],
      }),
    });
    loadPage();
    document.getElementById('available_segment_filters').appendChild(
      fieldOption('behaviors', 'hit_url_date', 'Visited URL date', 'datetime', { after: 'gte' })
    );

    window.mQuery = function (target) {
      const element = typeof target === 'string' ? document.querySelector(target) : target;
      return {
        html: (html) => { element.innerHTML = html; },
      };
    };
    window.Mautic.addLeadListFilter = () => {
      const panel = document.createElement('div');
      panel.id = 'leadlist_filters_0';
      panel.className = 'segment-filter';
      panel.innerHTML =
        '<select id="leadlist_filters_0_glue"><option value="and">and</option></select>' +
        '<select id="leadlist_filters_0_operator"><option value="gte">after</option></select>' +
        '<div id="leadlist_filters_0_properties"></div>';
      document.getElementById('leadlist_filters').appendChild(panel);
      window.Mautic.loadFilterForm(0, 'behaviors', 'hit_url_date', 'gte', (html) => {
        panel.querySelector('#leadlist_filters_0_properties').innerHTML = html;
      });
    };
    let loadCalls = 0;
    window.Mautic.loadFilterForm = (index, object, field, operator, callback) => {
      loadCalls += 1;
      const delay = loadCalls === 1 ? 30 : 0;
      setTimeout(() => callback('<input id="leadlist_filters_0_properties_filter" value="">'), delay);
    };
    window.Mautic.triggerOnPropertiesFormLoadedEvent = jest.fn();
    window.Mautic.updateFilterPositioning = jest.fn();
    window.Mautic.reorderSegmentFilters = jest.fn();

    document.querySelector('.ai-segment-assistant__prompt').value = 'Visited in the last 10 days';
    document.querySelector('.ai-segment-assistant__run').click();
    await new Promise((resolve) => setTimeout(resolve, 100));

    expect(document.getElementById('leadlist_filters_0_properties_filter').value).toBe('-10 days');
    expect(document.querySelector('.ai-segment-assistant__status').textContent).toBe('Filters are ready. Review them before saving.');
  });
});
