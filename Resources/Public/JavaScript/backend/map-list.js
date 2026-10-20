import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import Severity from '@typo3/backend/severity.js';
import labels from '~labels/map_magic.editor';
import { call } from '@wapplersystems/mapmagic/backend/api.js';

/**
 * Actions of the map list (Templates/Backend/Index.html): create, duplicate,
 * delete. Buttons carry data-mapmagic-action; the docheader "new" button too,
 * which is why the listener sits on the document.
 */
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-mapmagic-action]');
  if (!button) return;
  event.preventDefault();
  const { mapmagicAction: action, persistenceIdentifier, name, references } = button.dataset;
  if (action === 'create') openForm('create');
  if (action === 'duplicate') openForm('duplicate', { persistenceIdentifier, name });
  if (action === 'delete') confirmDelete(persistenceIdentifier, name, Number(references ?? 0));
});

function openForm(mode, source = {}) {
  const template = document.getElementById('mapmagic-create-form');
  const form = template.content.firstElementChild.cloneNode(true);
  if (mode === 'duplicate') {
    form.querySelector('[data-mapmagic-templates]').remove();
    form.elements.label.value = labels.get('duplicate.defaultName', [source.name]);
  }
  const modal = Modal.advanced({
    title: labels.get(mode === 'create' ? 'create.title' : 'duplicate.title'),
    content: form,
    size: Modal.sizes.small,
    buttons: [
      { text: labels.get('button.cancel'), btnClass: 'btn-default', name: 'cancel', trigger: (e, m) => m.hideModal() },
      { text: labels.get(mode === 'create' ? 'create.submit' : 'duplicate.submit'), btnClass: 'btn-primary', name: 'ok', active: true,
        trigger: () => form.requestSubmit() },
    ],
  });
  modal.addEventListener('typo3-modal-shown', () => form.elements.label.focus());
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = { label: form.elements.label.value, folder: form.elements.folder.value };
    try {
      const result =
        mode === 'create'
          ? await call('mapmagic_create', { ...payload, template: form.elements.template.value })
          : await call('mapmagic_duplicate', { ...payload, persistenceIdentifier: source.persistenceIdentifier });
      // The modal lives in the top frame; navigating this (module) frame would leave it open.
      modal.hideModal();
      window.location.href = result.editUrl;
    } catch (error) {
      Notification.error(labels.get(mode === 'create' ? 'create.failed' : 'duplicate.failed'), error.message);
    }
  });
}

function confirmDelete(persistenceIdentifier, name, references) {
  const message =
    references > 0 ? labels.get('delete.inUse', [name, references]) : labels.get('delete.confirm', [name]);
  const modal = Modal.confirm(labels.get('delete.title'), message, references > 0 ? Severity.error : Severity.warning, [
    { text: labels.get('button.cancel'), btnClass: 'btn-default', name: 'cancel', active: true },
    { text: labels.get('delete.submit'), btnClass: references > 0 ? 'btn-danger' : 'btn-warning', name: 'delete' },
  ]);
  modal.addEventListener('button.clicked', async (event) => {
    const clicked = event.target.getAttribute('name');
    modal.hideModal();
    if (clicked !== 'delete') return;
    try {
      await call('mapmagic_delete', { persistenceIdentifier });
      window.location.reload();
    } catch (error) {
      Notification.error(labels.get('delete.failed'), error.message);
    }
  });
}
