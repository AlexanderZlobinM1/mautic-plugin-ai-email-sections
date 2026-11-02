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

  function init() {
    document.querySelectorAll('.ai-profile-collection').forEach(function (collection) {
      if (collection.getAttribute('data-ai-profile-ready') === '1') {
        return;
      }

      var prototype = collection.getAttribute('data-prototype');
      if (!prototype) {
        return;
      }

      collection.setAttribute('data-ai-profile-ready', '1');
      var counter = collection.querySelectorAll('.ai-profile-row').length;
      var add = document.createElement('button');
      add.type = 'button';
      add.className = 'btn btn-secondary ai-profile-add';
      add.textContent = t('mautic.aiemailsections.profile.add', 'Add profile');
      collection.parentNode.appendChild(add);

      function wireRemove(row) {
        if (row.querySelector('.ai-profile-remove')) {
          return;
        }
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-danger ai-profile-remove';
        remove.textContent = t('mautic.aiemailsections.profile.remove', 'Remove profile');
        remove.addEventListener('click', function () {
          row.parentNode.removeChild(row);
        });
        row.appendChild(remove);
      }

      collection.querySelectorAll('.ai-profile-row').forEach(wireRemove);
      add.addEventListener('click', function () {
        var index = counter++;
        var html = prototype.replace(/__name__/g, index);
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        var row = wrapper.firstElementChild;
        if (!row) {
          return;
        }

        var id = row.querySelector('[name$="[id]"]');
        if (id) {
          id.value = 'profile-' + Date.now() + '-' + index;
        }
        collection.appendChild(row);
        wireRemove(row);
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
