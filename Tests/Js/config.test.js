const fs = require('fs');
const path = require('path');

const SOURCE = path.join(__dirname, '../../Assets/src/config.js');

function profileItem(index, id, name) {
  return `
    <div class="row form-group">
      <div class="col-md-4"></div>
      <div class="col-md-8"><div>
        <div class="ai-profile-row">
          <input name="profiles[${index}][id]" value="${id}">
          <div class="row form-group"><div class="col-md-4"><label>Name</label></div><div class="col-md-8"><input name="profiles[${index}][name]" value="${name}" required></div></div>
          <div class="row form-group"><div class="col-md-4"><label>Base URL</label></div><div class="col-md-8"><input name="profiles[${index}][base_url]" value=""></div></div>
        </div>
      </div></div>
    </div>`;
}

function loadConfig() {
  document.body.innerHTML = `
    <div id="IntegrationEditModal">
      <ul class="nav-tabs"><li id="details-tab"><a href="#details-container">Enabled/Auth</a></li><li id="features-tab"><a href="#features-container">Features</a></li></ul>
      <div id="details-container"><div class="row form-group"><div class="col-md-4"><label class="control-label">Old status label</label></div><div class="col-md-8">
        <input type="radio" id="integration_config_isPublished_1" name="integration_config[isPublished]" value="1" checked>
        <input type="radio" id="integration_config_isPublished_0" name="integration_config[isPublished]" value="0">
        <label id="integration_config_isPublished_label" aria-checked="true"></label>
      </div></div></div>
      <div id="features-container">
      <select name="integration_config[featureSettings][integration][default_profile]"><option value="local">Default</option></select>
      <div class="form-group"><label class="control-label">AI profiles</label><div class="controls">
        <div class="ai-profile-collection">${profileItem(0, 'local', 'Default')}</div>
      </div></div>
      </div>
    </div>`;
  document.querySelector('.ai-profile-collection').setAttribute(
    'data-prototype',
    profileItem('__name__', '', '')
  );
  window.Mautic = { translate: (key) => key };
  window.mQuery = jest.fn(() => ({}));
  window.eval(fs.readFileSync(SOURCE, 'utf8'));
  document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('profile configuration editor', () => {
  test('shows one profile editor and creates new profiles in place', () => {
    loadConfig();

    const collection = document.querySelector('.ai-profile-collection');
    expect(collection.closest('.form-group').classList.contains('ai-profile-form-group')).toBe(true);
    expect(document.querySelector('.ai-profile-selector')).not.toBeNull();
    expect(document.querySelector('.ai-profile-field--api_key input').name).toBe(
      'integration_config[apiKeys][profiles][local]'
    );
    expect(document.querySelectorAll('.ai-profile-item--active')).toHaveLength(1);

    document.querySelector('.ai-profile-create').click();

    const rows = collection.querySelectorAll('.ai-profile-row');
    expect(rows).toHaveLength(2);
    expect(rows[1].querySelector('[name$="[id]"]').value).toMatch(/^profile-/);
    expect(document.querySelectorAll('.ai-profile-item--active')).toHaveLength(1);
    expect(rows[1].closest('.ai-profile-item--active')).not.toBeNull();
  });

  test('saves the active profile into selectors instead of stacking dialogs', () => {
    loadConfig();
    document.querySelector('.ai-profile-create').click();

    const rows = document.querySelectorAll('.ai-profile-row');
    const newRow = rows[1];
    newRow.querySelector('[name$="[name]"]').value = 'Second profile';
    document.querySelector('.ai-profile-save').click();

    const manager = document.querySelector('.ai-profile-selector');
    expect(manager.options[manager.selectedIndex].textContent).toBe('Second profile');
    const defaultProfile = document.querySelector('[name$="[default_profile]"]');
    expect(Array.from(defaultProfile.options).some((option) => option.textContent === 'Second profile')).toBe(true);
    expect(document.querySelector('.ai-profile-status').textContent).toContain('Profile saved');
  });

  test('uses the native Mautic apply action to persist a profile', () => {
    loadConfig();
    const apply = document.createElement('button');
    apply.type = 'button';
    apply.id = 'integration_config_buttons_apply';
    const persist = jest.fn();
    apply.addEventListener('click', persist);
    document.querySelector('#IntegrationEditModal').appendChild(apply);

    document.querySelector('.ai-profile-save').click();

    expect(persist).toHaveBeenCalledTimes(1);
    expect(document.querySelector('.ai-profile-status').textContent).toContain('Saving profile');
  });

  test('switches between profiles and removes only the active profile', () => {
    loadConfig();
    document.querySelector('.ai-profile-create').click();
    const manager = document.querySelector('.ai-profile-selector');
    manager.value = 'local';
    manager.dispatchEvent(new Event('change'));

    const first = document.querySelectorAll('.ai-profile-row')[0];
    expect(first.closest('.ai-profile-item--active')).not.toBeNull();
    document.querySelector('.ai-profile-remove').click();

    expect(document.querySelectorAll('.ai-profile-row')).toHaveLength(1);
    expect(document.querySelector('.ai-profile-remove').disabled).toBe(true);
  });

  test('disables the Features tab and runtime when the integration is off', () => {
    loadConfig();
    const yes = document.querySelector('#integration_config_isPublished_1');
    const no = document.querySelector('#integration_config_isPublished_0');
    yes.checked = false;
    no.checked = true;
    no.dispatchEvent(new Event('change'));

    return new Promise((resolve) => setTimeout(resolve, 0)).then(() => {
      expect(document.querySelector('#features-tab').classList.contains('ai-tab-disabled')).toBe(true);
      expect(document.querySelector('#features-tab a').getAttribute('aria-disabled')).toBe('true');
      expect(document.querySelector('#features-container').hasAttribute('inert')).toBe(true);
      expect(window.MauticAiEmailSectionsDisabled).toBe(true);
    });
  });
});
