import { LitElement, html, nothing } from 'lit';
import labels from '~labels/map_magic.editor';
import Notification from '@typo3/backend/notification.js';
import '@typo3/backend/element/icon-element.js';
import '@typo3/backend/code-editor/element/code-mirror-element.js';
import { call } from '@wapplersystems/mapmagic/backend/api.js';
import { EditorModel } from '@wapplersystems/mapmagic/backend/editor/model.js';
import { pickFile } from '@wapplersystems/mapmagic/backend/editor/file-picker.js';
import '@wapplersystems/mapmagic/backend/editor/preview.js';
import '@wapplersystems/mapmagic/backend/editor/structure.js';
import '@wapplersystems/mapmagic/backend/editor/inspector.js';

/** Colors for rows created from data values (categories, icons); readable with white text and on maps. */
const PALETTE = ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf'];

/** How the YAML mode is passed to <typo3-t3editor-codemirror> (a JavaScriptModuleInstruction). */
const YAML_MODE = {
  name: '@wapplersystems/mapmagic/backend/editor/yaml-mode.js',
  flags: 2,
  exportName: 'yaml',
  items: [{ type: 'invoke', args: [] }],
};

/**
 * <mapmagic-editor config="{json}">: the map editor page.
 *
 * Owns the model, saving, undo/redo, the form/YAML switch and the context
 * the inspector widgets need (data properties, file picker, preview actions).
 */
export class MapMagicEditor extends LitElement {
  static properties = {
    config: { type: Object },
    _mode: { state: true },
    _saving: { state: true },
    _yamlError: { state: true },
    _feature: { state: true },
    _drawing: { state: true },
    _data: { state: true },
  };

  #model = null;
  #onModelChange = () => this.requestUpdate();
  #onKeyDown = (e) => this.#keyDown(e);
  #onBeforeUnload = (e) => {
    if (this.#model?.dirty) e.preventDefault();
  };

  constructor() {
    super();
    this._mode = 'form';
    this._saving = false;
    this._yamlError = null;
    this._feature = null;
    this._drawing = null;
    this._data = { properties: [], values: new Map(), featureCount: null };
  }

  createRenderRoot() {
    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    this.#model = new EditorModel(this.config.definition);
    this.#model.addEventListener('change', this.#onModelChange);
    this.#model.addEventListener('change', () => this.#refreshData());
    document.addEventListener('keydown', this.#onKeyDown);
    window.addEventListener('beforeunload', this.#onBeforeUnload);
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    document.removeEventListener('keydown', this.#onKeyDown);
    window.removeEventListener('beforeunload', this.#onBeforeUnload);
  }

  get #readOnly() {
    return Boolean(this.config.readOnly);
  }

  get #preview() {
    return this.querySelector('mapmagic-preview');
  }

  render() {
    const model = this.#model;
    return html`
      <div class="mapmagic-editor">
        <div class="mapmagic-editor__toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
          ${this.#readOnly
            ? html`<div class="alert alert-info mb-0 py-1 px-2">${labels.get('editor.readOnly')}</div>`
            : html`
                <button type="button" class="btn btn-primary" ?disabled=${this._saving || !model.dirty} @click=${() => this.#save()}>
                  <typo3-backend-icon identifier="actions-save" size="small"></typo3-backend-icon> ${labels.get('editor.save')}
                </button>
                <div class="btn-group">
                  <button type="button" class="btn btn-default" title=${labels.get('editor.undo')} ?disabled=${!model.canUndo} @click=${() => model.undo()}>
                    <typo3-backend-icon identifier="actions-undo" size="small"></typo3-backend-icon>
                  </button>
                  <button type="button" class="btn btn-default" title=${labels.get('editor.redo')} ?disabled=${!model.canRedo} @click=${() => model.redo()}>
                    <typo3-backend-icon identifier="actions-redo" size="small"></typo3-backend-icon>
                  </button>
                </div>
              `}
          <div class="btn-group ms-auto" role="group" aria-label=${labels.get('editor.mode')}>
            <button type="button" class="btn btn-default ${this._mode === 'form' ? 'active' : ''}" @click=${() => this.#setMode('form')}>
              ${labels.get('editor.modeForm')}
            </button>
            <button type="button" class="btn btn-default ${this._mode === 'yaml' ? 'active' : ''}" @click=${() => this.#setMode('yaml')}>
              <typo3-backend-icon identifier="actions-code" size="small"></typo3-backend-icon> YAML
            </button>
          </div>
          ${model.dirty ? html`<span class="badge badge-warning">${labels.get('editor.unsaved')}</span>` : nothing}
        </div>

        <div class="mapmagic-editor__columns ${this._mode === 'yaml' ? 'mapmagic-editor__columns--yaml' : ''}">
          ${this._mode === 'form'
            ? html`<aside class="mapmagic-editor__structure">
                <mapmagic-structure .model=${model} ?readOnly=${this.#readOnly} .presets=${this.config.basemapPresets}></mapmagic-structure>
              </aside>`
            : this.#renderYaml()}
          <main class="mapmagic-editor__preview">
            <mapmagic-preview
              .model=${model}
              .messages=${this.config.messages}
              @rendered=${() => this.#refreshData()}
              @feature-select=${(e) => (this._feature = e.detail)}
              @drawing-change=${(e) => {
                this._drawing = e.detail.layerIndex;
                if (e.detail.layerIndex === null) this._feature = null;
              }}
            ></mapmagic-preview>
          </main>
          ${this._mode === 'form'
            ? html`<aside class="mapmagic-editor__inspector">
                <mapmagic-inspector .model=${model} .context=${this.#context()} .feature=${this._feature}></mapmagic-inspector>
              </aside>`
            : nothing}
        </div>
      </div>
    `;
  }

  #renderYaml() {
    return html`
      <aside class="mapmagic-editor__yaml">
        <typo3-t3editor-codemirror .mode=${YAML_MODE} nolazyload ?readonly=${this.#readOnly}>
          <textarea class="form-control"></textarea>
        </typo3-t3editor-codemirror>
        ${this._yamlError
          ? html`<div class="alert alert-danger mt-2 mb-0">
              ${this._yamlError.line > 0 ? labels.get('yaml.errorLine', [this._yamlError.line]) + ' ' : ''}${this._yamlError.message}
            </div>`
          : nothing}
        ${!this.#readOnly
          ? html`<button type="button" class="btn btn-default mt-2" @click=${() => this.#applyYaml()}>${labels.get('yaml.apply')}</button>`
          : nothing}
      </aside>
    `;
  }

  #context() {
    const model = this.#model;
    const selected = model.selection.kind === 'layer' ? model.definition.layers[model.selection.index] : null;
    return {
      t: (key) => labels.get(key) || key,
      readOnly: this.#readOnly,
      kind: model.definition.kind ?? 'geo',
      properties: this._data.properties,
      distinctValues: (property) => this._data.values.get(property) ?? [],
      featureCount: this._data.featureCount,
      presets: this.config.basemapPresets,
      palette: PALETTE,
      pickFile: (allowed) => pickFile(allowed),
      iconHtml: (icon) => this.#iconHtml(icon),
      captureView: () => this.#captureView(),
      startDrawing: () => selected && this.#preview?.startDrawing(model.selection.index),
      setFeatureProperties: (index, properties) => this.#preview?.setFeatureProperties(index, properties),
      notify: (message) => Notification.warning(labels.get('editor.title'), message, 5),
    };
  }

  /** Property names and values of the selected layer's data, for suggestions in the inspector. */
  async #refreshData() {
    const model = this.#model;
    const layer = model.selection.kind === 'layer' ? model.definition.layers[model.selection.index] : null;
    if (!layer) {
      this._data = { properties: [], values: new Map(), featureCount: null };
      return;
    }
    const inline = 'data' in (layer.source ?? {});
    const data = inline ? layer.source.data : await this.#preview?.layerData(layer.id);
    const values = new Map();
    for (const feature of data?.features ?? []) {
      for (const [key, value] of Object.entries(feature?.properties ?? {})) {
        if (!values.has(key)) values.set(key, new Set());
        for (const v of Array.isArray(value) ? value : [value]) {
          if (v !== null && v !== undefined && v !== '' && typeof v !== 'object') values.get(key).add(String(v));
        }
      }
    }
    this._data = {
      properties: [...values.keys()].sort((a, b) => a.localeCompare(b, 'de')),
      values: new Map([...values].map(([k, set]) => [k, [...set].sort((a, b) => a.localeCompare(b, 'de'))])),
      featureCount: inline ? (layer.source.data?.features?.length ?? 0) : null,
    };
  }

  #iconHtml(icon) {
    if (!icon || icon.type === 'pin') {
      return window.MapMagic.pinIcon({ type: 'pin', ...(icon ?? {}), size: 24 }).html;
    }
    const img = document.createElement('img');
    img.src = icon.url ?? '';
    img.alt = '';
    img.height = 24;
    return img.outerHTML;
  }

  #captureView() {
    const view = this.#preview?.getView();
    if (!view) return;
    this.#model.update((d) => {
      d.view = { ...(d.view ?? {}), center: view.center, zoom: view.zoom };
    });
  }

  async #save() {
    if (this._mode === 'yaml' && !(await this.#applyYaml())) return;
    this._saving = true;
    try {
      await call('mapmagic_save', {
        persistenceIdentifier: this.config.persistenceIdentifier,
        definition: this.#model.definition,
      });
      this.#model.markSaved();
      Notification.success(labels.get('editor.saved'), '', 3);
    } catch (error) {
      Notification.error(labels.get('editor.saveFailed'), error.message);
    } finally {
      this._saving = false;
    }
  }

  async #setMode(mode) {
    if (mode === this._mode) return;
    if (this._mode === 'yaml' && !this.#readOnly && !(await this.#applyYaml())) return;
    this.#preview?.stopDrawing();
    this._mode = mode;
    this._yamlError = null;
    if (mode === 'yaml') {
      const { yaml } = await call('mapmagic_yaml_dump', { definition: this.#model.definition });
      await this.updateComplete;
      const editor = this.querySelector('typo3-t3editor-codemirror');
      // The element reads its initial document from the textarea; later updates go through setContent().
      editor.querySelector('textarea').value = yaml;
      editor.setContent?.(yaml);
      this.#yamlSnapshot = yaml;
    }
  }

  #yamlSnapshot = '';

  /** Parses the YAML on the server and replaces the definition. Returns false on a syntax error. */
  async #applyYaml() {
    const editor = this.querySelector('typo3-t3editor-codemirror');
    if (!editor) return true;
    const yaml = editor.getContent?.() ?? editor.querySelector('textarea').value;
    if (yaml === this.#yamlSnapshot) return true;
    try {
      const { definition } = await call('mapmagic_yaml_parse', { yaml });
      this.#model.replace(definition);
      this.#yamlSnapshot = yaml;
      this._yamlError = null;
      return true;
    } catch (error) {
      this._yamlError = { message: error.message, line: error.data?.line ?? 0 };
      return false;
    }
  }

  #keyDown(event) {
    if (!(event.ctrlKey || event.metaKey) || this.#readOnly) return;
    const key = event.key.toLowerCase();
    if (key === 's') {
      event.preventDefault();
      void this.#save();
      return;
    }
    // Inside text fields and the code editor, Ctrl+Z belongs to the field.
    const target = event.target;
    if (target.closest?.('input, textarea, select, [contenteditable], typo3-t3editor-codemirror')) return;
    if (key === 'z' && !event.shiftKey) {
      event.preventDefault();
      this.#model.undo();
    } else if (key === 'y' || (key === 'z' && event.shiftKey)) {
      event.preventDefault();
      this.#model.redo();
    }
  }
}

customElements.define('mapmagic-editor', MapMagicEditor);
