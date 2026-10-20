import { LitElement, html, nothing } from 'lit';
import labels from '~labels/map_magic.editor';
import { nodeSchemas, schemaKeyFor } from '@wapplersystems/mapmagic/backend/editor/schema.js';
import { renderField } from '@wapplersystems/mapmagic/backend/editor/fields.js';

/**
 * Right column: the settings of the selected node, generated from
 * schema.js, and — while drawing — the properties of the clicked feature.
 *
 * `context` is built by the app (data properties, file picker, preview
 * actions); this component only adds the node-specific parts.
 */
export class MapMagicInspector extends LitElement {
  static properties = {
    model: { attribute: false },
    context: { attribute: false },
    feature: { attribute: false },
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
    const model = this.model;
    const basePath = model.selectionPath;
    const node = model.get(basePath) ?? {};
    const groups = nodeSchemas[schemaKeyFor(model.selection, node)] ?? [];
    const ctx = {
      ...this.context,
      updateNode: (mutate) =>
        model.update((draft) => mutate(basePath.reduce((n, step) => n[step], draft))),
    };

    return html`
      ${this.#renderFeature()}
      ${groups.map((group) => {
        const fields = group.fields.filter((f) => !f.when || f.when(node));
        if (fields.length === 0) return nothing;
        return html`
          <fieldset class="mb-3">
            <legend class="h6">${labels.get(group.group)}</legend>
            ${fields.map((descriptor) => {
              const path = [...basePath, ...descriptor.key.split('.')];
              return renderField(
                descriptor,
                model.get(path),
                (value, options) => this.#change(path, descriptor, value, options),
                ctx,
                node,
              );
            })}
          </fieldset>
        `;
      })}
    `;
  }

  #change(path, descriptor, value, options = {}) {
    // Layer ids are references (filter boxes, legend sections); they must stay unique.
    if (descriptor.key === 'id' && value && value !== this.model.get(path) && this.model.ids.includes(value)) {
      this.context.notify?.(labels.get('error.duplicateId', [value]));
      this.requestUpdate();
      return;
    }
    this.model.set(path, value, options);
  }

  /** Key/value editor for the feature selected on the map while drawing. */
  #renderFeature() {
    const f = this.feature;
    if (!f || f.featureIndex === null) return nothing;
    const path = ['layers', f.layerIndex, 'source', 'data', 'features', f.featureIndex, 'properties'];
    const properties = this.model.get(path) ?? {};
    const entries = Object.entries(properties);
    const write = (next) => {
      this.model.set(path, next, { coalesce: `feature-${f.layerIndex}-${f.featureIndex}` });
      this.context.setFeatureProperties?.(f.featureIndex, next);
    };
    const rename = (oldKey, newKey) => {
      if (!newKey || newKey === oldKey || newKey in properties) return;
      write(Object.fromEntries(entries.map(([k, v]) => [k === oldKey ? newKey : k, v])));
    };
    const suggestions = this.context.properties.filter((p) => !(p in properties));

    return html`
      <fieldset class="mb-3 mapmagic-editor__feature">
        <legend class="h6">${labels.get('feature.title', [f.featureIndex + 1])}</legend>
        <table class="table table-sm mb-2">
          <thead><tr><th>${labels.get('feature.key')}</th><th>${labels.get('feature.value')}</th><th></th></tr></thead>
          <tbody>
            ${entries.map(
              ([key, value]) => html`<tr>
                <td><input class="form-control form-control-sm" .value=${key} ?disabled=${this.context.readOnly}
                  @change=${(e) => rename(key, e.target.value.trim())}></td>
                <td><input class="form-control form-control-sm" .value=${value == null ? '' : String(value)} ?disabled=${this.context.readOnly}
                  @input=${(e) => write({ ...properties, [key]: e.target.value })}></td>
                <td><button type="button" class="btn btn-default btn-sm" title=${labels.get('widget.remove')} ?disabled=${this.context.readOnly}
                  @click=${() => write(Object.fromEntries(entries.filter(([k]) => k !== key)))}>×</button></td>
              </tr>`,
            )}
          </tbody>
        </table>
        <div class="d-flex flex-wrap gap-1">
          ${['name', ...suggestions.filter((p) => p !== 'name')]
            .filter((p) => !(p in properties))
            .slice(0, 8)
            .map(
              (p) => html`<button type="button" class="btn btn-default btn-sm" ?disabled=${this.context.readOnly}
                @click=${() => write({ ...properties, [p]: '' })}>+ ${p}</button>`,
            )}
        </div>
      </fieldset>
    `;
  }
}

customElements.define('mapmagic-inspector', MapMagicInspector);
