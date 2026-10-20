import { LitElement, html, nothing } from 'lit';
import labels from '~labels/map_magic.editor';
import '@typo3/backend/element/icon-element.js';
import { layerTypes, newLayer } from '@wapplersystems/mapmagic/backend/editor/schema.js';

/**
 * Left column: the map, its basemaps and its layers. Selecting a row shows
 * it in the inspector; layers can be added, reordered and removed here.
 * Layer order is drawing order (later = on top), as in the renderer.
 */
export class MapMagicStructure extends LitElement {
  static properties = {
    model: { attribute: false },
    readOnly: { type: Boolean },
    presets: { attribute: false },
  };

  #onModelChange = () => this.requestUpdate();

  createRenderRoot() {
    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    this.model.addEventListener('change', this.#onModelChange);
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    this.model.removeEventListener('change', this.#onModelChange);
  }

  render() {
    const def = this.model.definition;
    const sel = this.model.selection;
    const isImage = def.kind === 'image';
    return html`
      <div class="list-group mb-3">
        ${this.#row({ kind: 'map' }, sel, isImage ? 'actions-image' : 'actions-map', def.label || labels.get('structure.map'))}
      </div>

      <div class="d-flex align-items-center mb-1">
        <h3 class="h6 mb-0 flex-grow-1">${labels.get('structure.basemaps')}</h3>
        ${!isImage && !this.readOnly ? this.#addBasemapButton() : nothing}
      </div>
      <div class="list-group mb-3">
        ${def.basemaps.map((basemap, index) =>
          this.#row(
            { kind: 'basemap', index },
            sel,
            basemap.type === 'image' ? 'actions-image' : 'actions-globe',
            this.#basemapLabel(basemap),
            !isImage && def.basemaps.length > 1 ? this.#itemActions('basemaps', index, def.basemaps.length) : nothing,
          ),
        )}
      </div>

      <div class="d-flex align-items-center mb-1">
        <h3 class="h6 mb-0 flex-grow-1">${labels.get('structure.layers')}</h3>
        ${!this.readOnly ? this.#addLayerButton() : nothing}
      </div>
      <div class="list-group">
        ${def.layers.length === 0
          ? html`<div class="list-group-item text-body-secondary">${labels.get('structure.noLayers')}</div>`
          : nothing}
        ${def.layers.map((layer, index) =>
          this.#row(
            { kind: 'layer', index },
            sel,
            layerTypes.find((t) => t.type === layer.type)?.icon ?? 'actions-circle',
            layer.label || layer.id || labels.get(`layer.${layer.type}`),
            this.#itemActions('layers', index, def.layers.length),
            layer.visible === false,
          ),
        )}
      </div>
    `;
  }

  #row(selection, current, icon, text, actions = nothing, muted = false) {
    const active =
      selection.kind === current.kind && (selection.kind === 'map' || selection.index === current.index);
    return html`
      <div class="list-group-item list-group-item-action d-flex align-items-center gap-2 ${active ? 'active' : ''}"
        role="button" tabindex="0" aria-current=${active ? 'true' : 'false'}
        @click=${() => this.model.select(selection)}
        @keydown=${(e) => (e.key === 'Enter' || e.key === ' ') && (e.preventDefault(), this.model.select(selection))}>
        <typo3-backend-icon identifier=${icon} size="small"></typo3-backend-icon>
        <span class="flex-grow-1 text-truncate ${muted ? 'opacity-50' : ''}">${text}</span>
        ${actions}
      </div>
    `;
  }

  #itemActions(list, index, length) {
    if (this.readOnly) return nothing;
    const stop = (fn) => (e) => {
      // The buttons sit inside the selectable row.
      e.stopPropagation();
      fn();
    };
    return html`
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-default" title=${labels.get('structure.up')} ?disabled=${index === 0}
          @click=${stop(() => this.#move(list, index, -1))}>
          <typo3-backend-icon identifier="actions-arrow-up" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-default" title=${labels.get('structure.down')} ?disabled=${index === length - 1}
          @click=${stop(() => this.#move(list, index, 1))}>
          <typo3-backend-icon identifier="actions-arrow-down" size="small"></typo3-backend-icon>
        </button>
        <button type="button" class="btn btn-default" title=${labels.get('structure.remove')}
          @click=${stop(() => this.#remove(list, index))}>
          <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
        </button>
      </div>
    `;
  }

  #addLayerButton() {
    return html`
      <div class="dropdown">
        <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon> ${labels.get('structure.addLayer')}
        </button>
        <ul class="dropdown-menu">
          ${layerTypes.map(
            (t) => html`<li>
              <button type="button" class="dropdown-item" @click=${() => this.#addLayer(t.type)}>
                <typo3-backend-icon identifier=${t.icon} size="small"></typo3-backend-icon> ${labels.get(t.label)}
              </button>
            </li>`,
          )}
        </ul>
      </div>
    `;
  }

  #addBasemapButton() {
    const used = new Set(this.model.definition.basemaps.map((b) => b.preset).filter(Boolean));
    const free = Object.entries(this.presets ?? {}).filter(([name]) => !used.has(name));
    return html`
      <div class="dropdown">
        <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon> ${labels.get('structure.addBasemap')}
        </button>
        <ul class="dropdown-menu">
          ${free.map(
            ([name, preset]) => html`<li>
              <button type="button" class="dropdown-item" @click=${() => this.#addBasemap({ preset: name })}>${preset.label}</button>
            </li>`,
          )}
          <li>
            <button type="button" class="dropdown-item"
              @click=${() => this.#addBasemap({ type: 'xyz', id: this.model.uniqueId('tiles'), label: labels.get('structure.customTiles'), url: '' })}>
              ${labels.get('preset.custom')}
            </button>
          </li>
        </ul>
      </div>
    `;
  }

  #basemapLabel(basemap) {
    if (basemap.label) return basemap.label;
    if (basemap.preset) return this.presets?.[basemap.preset]?.label ?? basemap.preset;
    return basemap.id ?? labels.get('structure.basemap');
  }

  #addLayer(type) {
    const id = this.model.uniqueId(type);
    this.model.update((d) => d.layers.push(newLayer(type, id)));
    this.model.select({ kind: 'layer', index: this.model.definition.layers.length - 1 });
  }

  #addBasemap(basemap) {
    this.model.update((d) => d.basemaps.push(basemap));
    this.model.select({ kind: 'basemap', index: this.model.definition.basemaps.length - 1 });
  }

  #move(list, index, delta) {
    this.model.update((d) => {
      const [item] = d[list].splice(index, 1);
      d[list].splice(index + delta, 0, item);
    });
    this.model.select({ kind: list === 'layers' ? 'layer' : 'basemap', index: index + delta });
  }

  #remove(list, index) {
    this.model.update((d) => d[list].splice(index, 1));
    // model clamps an out-of-range selection; select the map when the list is empty
    if (this.model.definition[list].length === 0) this.model.select({ kind: 'map' });
  }
}

customElements.define('mapmagic-structure', MapMagicStructure);
