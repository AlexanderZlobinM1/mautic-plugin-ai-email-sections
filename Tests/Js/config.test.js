const fs = require('fs');
const path = require('path');

const SOURCE = path.join(__dirname, '../../Assets/src/config.js');

function loadConfig() {
  document.body.innerHTML = '<div class="ai-profile-collection"><div class="ai-profile-row"><input name="profiles[0][id]" value="local"></div></div>';
  document.querySelector('.ai-profile-collection').setAttribute(
    'data-prototype',
    '<div class="ai-profile-row"><input name="profiles[__name__][id]"></div>'
  );
  window.Mautic = { translate: (key) => key };
  window.mQuery = jest.fn(() => ({}));
  window.eval(fs.readFileSync(SOURCE, 'utf8'));
  document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('profile configuration editor', () => {
  test('adds unlimited-style prototype rows and assigns stable ids', () => {
    loadConfig();

    const collection = document.querySelector('.ai-profile-collection');
    const add = document.querySelector('.ai-profile-add');
    expect(add).not.toBeNull();

    add.click();

    const rows = collection.querySelectorAll('.ai-profile-row');
    expect(rows).toHaveLength(2);
    expect(rows[1].querySelector('input').value).toMatch(/^profile-/);
    expect(rows[0].querySelector('.ai-profile-remove')).not.toBeNull();
  });
});
