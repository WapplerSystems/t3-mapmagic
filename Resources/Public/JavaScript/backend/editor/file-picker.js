import Modal from '@typo3/backend/modal.js';

/**
 * Opens the TYPO3 file browser and resolves with a `t3://file?uid=…`
 * reference, or null when closed without a choice. `t3://` survives file
 * renames and moves, unlike a path, and the server resolves it to the public
 * URL when rendering.
 *
 * @param {string} allowed comma-separated file extensions, e.g. "svg,png"
 * @returns {Promise<string|null>}
 */
export function pickFile(allowed) {
  return new Promise((resolve) => {
    const params = new URLSearchParams({
      mode: 'file',
      fieldReference: 'mapmagic',
      allowedTypes: allowed,
      // Events instead of postMessage: the answer arrives on the modal itself,
      // no global message listener to clean up.
      useEvents: '1',
    });
    let result = null;
    const modal = Modal.advanced({
      type: Modal.types.iframe,
      content: TYPO3.settings.Wizards.elementBrowserUrl + '&' + params.toString(),
      size: Modal.sizes.large,
    });
    modal.addEventListener('typo3:element-browser:message', (event) => {
      const { actionName, value, close } = event.detail ?? {};
      if (actionName !== 'typo3:elementBrowser:elementAdded') return;
      // value is "<uid>" or "sys_file_<uid>" depending on the browser version.
      const uid = String(value).split('_').pop();
      result = `t3://file?uid=${uid}`;
      if (close !== false) modal.hideModal();
    });
    modal.addEventListener('typo3-modal-hidden', () => resolve(result));
  });
}
