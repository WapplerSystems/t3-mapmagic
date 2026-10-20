import { html, nothing } from 'lit';
import { live } from 'lit/directives/live.js';
import { unsafeHTML } from 'lit/directives/unsafe-html.js';
import { ifDefined } from 'lit/directives/if-defined.js';

let uidCounter = 0;
const idByKey = new Map();

function fieldId(key) {
  const k = String(key || '').replace(/[^a-zA-Z0-9_-]/g, '-');
  if (!k) return 'field-' + (++uidCounter);
  if (!idByKey.has(k)) idByKey.set(k, 'field-' + (++uidCounter));
  return idByKey.get(k);
}

export function renderField(descriptor, value, onChange, ctx, node) {
  const id = fieldId(descriptor.key || descriptor.type + '-' + descriptor.label);
  const t = ctx.t;
  const disabled = ctx.readOnly;

  const rootLabel = descriptor.label
    ? html`<label class="form-label" for="${id}">${t(descriptor.label)}</label>`
    : nothing;
  const help = descriptor.help
    ? html`<div class="form-text">${t(descriptor.help)}</div>`
    : nothing;

  const wrap = (body) => html`
    <div class="form-group mb-3">
      ${rootLabel}
      ${body}
      ${help}
    </div>
  `;

  const onChangeCoalesced = (newValue) =>
    onChange(newValue, { coalesce: descriptor.key });

  const numberFromRaw = (raw) => {
    if (raw === '') return undefined;
    const n = Number(raw);
    return Number.isNaN(n) ? undefined : n;
  };

  const colorControl = (val, changeFn, idBase) => {
    const isHex = /^#[0-9a-f]{6}$/i.test(val || '');
    const previewColor = isHex ? val : '#000000';
    return html`
      <div class="input-group input-group-sm">
        <input
          type="color"
          class="form-control form-control-color"
          id="${idBase}-color"
          ?disabled=${disabled}
          style=${isHex ? '' : 'opacity: 0.35'}
          .value=${live(previewColor)}
          @change=${(e) => changeFn(e.target.value, false)}
        />
        <input
          type="text"
          class="form-control form-control-sm"
          id="${idBase}-text"
          ?disabled=${disabled}
          placeholder=${t('widget.defaultColor')}
          .value=${live(val ?? '')}
          @input=${(e) => changeFn(e.target.value, true)}
        />
      </div>
    `;
  };

  const fileControl = (val, changeFn, idBase, allowed, placeholder) => html`
    <div class="input-group input-group-sm">
      <input
        type="text"
        class="form-control"
        id="${idBase}-text"
        ?disabled=${disabled}
        .value=${live(val ?? '')}
        placeholder=${ifDefined(placeholder)}
        @input=${(e) => changeFn(e.target.value, true)}
      />
      <button
        type="button"
        class="btn btn-default btn-sm"
        ?disabled=${disabled}
        @click=${async () => {
          const result = await ctx.pickFile(allowed);
          if (result !== null) changeFn(result, false);
        }}
      >
        ${t('widget.pickFile')}
      </button>
    </div>
  `;

  const datalist = (inputId, values) => html`
    <datalist id="${inputId}">
      ${values.map((p) => html`<option value=${p}></option>`)}
    </datalist>
  `;

  const iconCore = (val, onIconChange, allowFalse, idBase) => {
    const currentType =
      val === false ? 'hidden' : val?.type === 'image' ? 'image' : val?.type === 'pin' ? 'pin' : '';
    const previewIcon = val === false ? null : (val ?? { type: 'pin' });

    const changeType = (e) => {
      const next = e.target.value;
      if (next === '') onIconChange(undefined);
      else if (next === 'pin') onIconChange({ type: 'pin' });
      else if (next === 'image') onIconChange({ type: 'image', url: '', size: [32, 32] });
      else if (next === 'hidden') onIconChange(false);
    };

    const pinVal = val && val !== false && val.type === 'pin' ? val : { type: 'pin' };
    const imgVal = val && val !== false && val.type === 'image'
      ? val
      : { type: 'image', url: '', size: [32, 32] };

    const updatePin = (prop, newValue, coalesce) => {
      const base = val && val !== false && val.type === 'pin' ? val : { type: 'pin' };
      const next = { ...base };
      if (newValue === undefined || newValue === '') delete next[prop];
      else next[prop] = newValue;
      onIconChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
    };

    const updateImage = (patch, coalesce) => {
      const base = val && val !== false && val.type === 'image'
        ? val
        : { type: 'image', url: '', size: [32, 32] };
      const next = { ...base, ...patch };
      if (next.url === undefined || next.url === '') delete next.url;
      if (next.size === undefined || !Array.isArray(next.size)) delete next.size;
      onIconChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
    };

    return html`
      <div class="d-flex align-items-center gap-2 mb-2">
        ${previewIcon ? html`<span class="me-2">${unsafeHTML(ctx.iconHtml(previewIcon))}</span>` : nothing}
        <div class="flex-grow-1">
          <select
            class="form-select form-select-sm"
            id="${idBase}-type"
            ?disabled=${disabled}
            @change=${changeType}
          >
            <option value="" ?selected=${currentType === ''}>${t('icon.default')}</option>
            <option value="pin" ?selected=${currentType === 'pin'}>${t('icon.pin')}</option>
            <option value="image" ?selected=${currentType === 'image'}>${t('icon.image')}</option>
            ${allowFalse ? html`<option value="hidden" ?selected=${currentType === 'hidden'}>${t('icon.hidden')}</option>` : nothing}
          </select>
        </div>
      </div>

      ${currentType === 'pin'
        ? html`
            <div class="mb-2">
              <label class="form-label small" for="${idBase}-pin-color">${t('widget.color')}</label>
              ${colorControl(pinVal.color, (raw, coal) => updatePin('color', raw, coal), idBase + '-pin-color')}
            </div>
            <div class="d-flex gap-2">
              <div>
                <label class="form-label small" for="${idBase}-glyph">${t('widget.glyph')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${idBase}-glyph"
                  maxlength="2"
                  ?disabled=${disabled}
                  .value=${live(pinVal.glyph ?? '')}
                  @input=${(e) => updatePin('glyph', e.target.value.slice(0, 2), true)}
                />
              </div>
              <div>
                <label class="form-label small" for="${idBase}-size">${t('widget.size')}</label>
                <input
                  type="number"
                  class="form-control form-control-sm"
                  id="${idBase}-size"
                  min="0"
                  step="1"
                  placeholder="41"
                  ?disabled=${disabled}
                  .value=${live(pinVal.size ?? '')}
                  @input=${(e) => {
                    const raw = e.target.value;
                    const n = numberFromRaw(raw);
                    updatePin('size', n, true);
                  }}
                />
              </div>
            </div>
          `
        : nothing}

      ${currentType === 'image'
        ? html`
            <div class="mb-2">
              <label class="form-label small" for="${idBase}-image-url">${t('widget.url')}</label>
              ${fileControl(
                imgVal.url,
                (raw, coal) => updateImage({ url: raw }, coal),
                idBase + '-image-url',
                'svg,png,jpg,jpeg,webp,gif'
              )}
            </div>
            <div class="d-flex gap-2">
              <div>
                <label class="form-label small" for="${idBase}-width">${t('widget.width')}</label>
                <input
                  type="number"
                  class="form-control form-control-sm"
                  id="${idBase}-width"
                  min="0"
                  step="1"
                  ?disabled=${disabled}
                  .value=${live(Array.isArray(imgVal.size) ? imgVal.size[0] ?? '' : '')}
                  @input=${(e) => {
                    const raw = e.target.value;
                    const current = Array.isArray(imgVal.size) ? imgVal.size[1] : undefined;
                    const w = numberFromRaw(raw);
                    if (w === undefined || current === undefined) updateImage({ size: undefined }, true);
                    else updateImage({ size: [w, current] }, true);
                  }}
                />
              </div>
              <div>
                <label class="form-label small" for="${idBase}-height">${t('widget.height')}</label>
                <input
                  type="number"
                  class="form-control form-control-sm"
                  id="${idBase}-height"
                  min="0"
                  step="1"
                  ?disabled=${disabled}
                  .value=${live(Array.isArray(imgVal.size) ? imgVal.size[1] ?? '' : '')}
                  @input=${(e) => {
                    const raw = e.target.value;
                    const current = Array.isArray(imgVal.size) ? imgVal.size[0] : undefined;
                    const h = numberFromRaw(raw);
                    if (h === undefined || current === undefined) updateImage({ size: undefined }, true);
                    else updateImage({ size: [current, h] }, true);
                  }}
                />
              </div>
            </div>
          `
        : nothing}
    `;
  };

  switch (descriptor.type) {
    case 'text': {
      return wrap(html`
        <input
          type="text"
          class="form-control form-control-sm"
          id="${id}"
          ?disabled=${disabled}
          ?required=${descriptor.required}
          placeholder=${ifDefined(descriptor.placeholder)}
          .value=${live(value ?? '')}
          @input=${(e) => {
            const raw = e.target.value;
            if (raw === '' && !descriptor.required) onChange(undefined, { coalesce: descriptor.key });
            else onChange(raw, { coalesce: descriptor.key });
          }}
        />
      `);
    }

    case 'textarea': {
      return wrap(html`
        <textarea
          class="form-control form-control-sm"
          id="${id}"
          rows=${descriptor.rows ?? 3}
          ?disabled=${disabled}
          ?required=${descriptor.required}
          placeholder=${ifDefined(descriptor.placeholder)}
          .value=${live(value ?? '')}
          @input=${(e) => {
            const raw = e.target.value;
            if (raw === '' && !descriptor.required) onChange(undefined, { coalesce: descriptor.key });
            else onChange(raw, { coalesce: descriptor.key });
          }}
        ></textarea>
      `);
    }

    case 'template': {
      const insertTemplate = (e, name) => {
        const ta = e.currentTarget.closest('.form-group').querySelector('textarea');
        const start = ta.selectionStart ?? ta.value.length;
        const end = ta.selectionEnd ?? ta.value.length;
        const token = `{${name}}`;
        const next = ta.value.slice(0, start) + token + ta.value.slice(end);
        onChange(next);
      };
      return wrap(html`
        <textarea
          class="form-control form-control-sm"
          id="${id}"
          rows=${descriptor.rows ?? 3}
          ?disabled=${disabled}
          ?required=${descriptor.required}
          placeholder=${ifDefined(descriptor.placeholder)}
          .value=${live(value ?? '')}
          @input=${(e) => {
            const raw = e.target.value;
            if (raw === '' && !descriptor.required) onChange(undefined, { coalesce: descriptor.key });
            else onChange(raw, { coalesce: descriptor.key });
          }}
        ></textarea>
        <div class="d-flex flex-wrap gap-1 mt-1">
          ${ctx.properties.map(
            (name) => html`
              <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${(e) => insertTemplate(e, name)}>
                {${name}}
              </button>
            `
          )}
        </div>
      `);
    }

    case 'number': {
      return wrap(html`
        <input
          type="number"
          class="form-control form-control-sm"
          id="${id}"
          ?disabled=${disabled}
          min=${ifDefined(descriptor.min)}
          max=${ifDefined(descriptor.max)}
          step=${ifDefined(descriptor.step)}
          placeholder=${ifDefined(descriptor.default !== undefined ? String(descriptor.default) : undefined)}
          .value=${live(value ?? '')}
          @input=${(e) => {
            const raw = e.target.value;
            const n = numberFromRaw(raw);
            if (n === undefined) {
              onChange(undefined, { coalesce: descriptor.key });
              return;
            }
            if (descriptor.default !== undefined && n === descriptor.default) {
              onChange(undefined, { coalesce: descriptor.key });
              return;
            }
            onChange(n, { coalesce: descriptor.key });
          }}
        />
      `);
    }

    case 'checkbox': {
      const checked = value ?? descriptor.default ?? false;
      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(checked)}
            @change=${(e) => {
              const next = e.target.checked;
              const def = descriptor.default ?? false;
              onChange(next === def ? undefined : next);
            }}
          />
        </div>
      `);
    }

    case 'select': {
      const options = descriptor.options ?? [];
      const current = value ?? descriptor.default;
      const currentIndex = options.findIndex((opt) => opt.value === current);
      return wrap(html`
        <select
          class="form-select form-select-sm"
          id="${id}"
          ?disabled=${disabled}
          @change=${(e) => {
            const idx = Number(e.target.value);
            const opt = options[idx];
            const next = opt ? opt.value : undefined;
            onChange(next === descriptor.default ? undefined : next);
          }}
        >
          ${options.map(
            // ?selected instead of .value on the select: the options are rendered
            // after the select's own bindings, so .value would find no match.
            (opt, idx) => html`<option value="${idx}" ?selected=${idx === currentIndex}>${t(opt.label)}</option>`
          )}
        </select>
      `);
    }

    case 'color': {
      return wrap(html`
        ${colorControl(
          value,
          (raw, coalesce) => {
            const next = raw === '' ? undefined : raw;
            onChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
          },
          id
        )}
      `);
    }

    case 'file': {
      return wrap(html`
        ${fileControl(
          value,
          (raw, coalesce) => {
            const next = raw === '' ? undefined : raw;
            onChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
          },
          id,
          descriptor.allowed,
          descriptor.placeholder
        )}
      `);
    }

    case 'center': {
      const xVal = Array.isArray(value) && typeof value[0] === 'number' ? value[0] : '';
      const yVal = Array.isArray(value) && typeof value[1] === 'number' ? value[1] : '';
      const isImage = ctx.kind === 'image';

      const handleCenterInput = (e) => {
        const root = e.currentTarget.closest('.form-group');
        const inputs = root.querySelectorAll('input[type="number"]');
        const rawX = inputs[0].value;
        const rawY = inputs[1].value;
        const x = numberFromRaw(rawX);
        const y = numberFromRaw(rawY);
        if (x === undefined && y === undefined) {
          onChange(undefined, { coalesce: descriptor.key });
          return;
        }
        onChange([x ?? 0, y ?? 0], { coalesce: descriptor.key });
      };

      return wrap(html`
        <div class="d-flex gap-2">
          <div>
            <label class="form-label small" for="${id}-x">${t(isImage ? 'widget.x' : 'widget.lng')}</label>
            <input
              type="number"
              class="form-control form-control-sm"
              id="${id}-x"
              step="any"
              ?disabled=${disabled}
              .value=${live(xVal)}
              @change=${handleCenterInput}
            />
          </div>
          <div>
            <label class="form-label small" for="${id}-y">${t(isImage ? 'widget.y' : 'widget.lat')}</label>
            <input
              type="number"
              class="form-control form-control-sm"
              id="${id}-y"
              step="any"
              ?disabled=${disabled}
              .value=${live(yVal)}
              @change=${handleCenterInput}
            />
          </div>
        </div>
        <div class="d-flex gap-2 mt-1">
          <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${() => ctx.captureView()}>
            ${t('widget.captureView')}
          </button>
          ${Array.isArray(value)
            ? html`<button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${() => onChange(undefined)}>
                ${t('widget.clear')}
              </button>`
            : nothing}
        </div>
        ${!Array.isArray(value) ? html`<div class="form-text">${t('widget.centerAuto')}</div>` : nothing}
      `);
    }

    case 'preset': {
      const presets = Object.entries(ctx.presets ?? {});
      return wrap(html`
        <select
          class="form-select form-select-sm"
          id="${id}"
          ?disabled=${disabled}
          @change=${(e) => {
            const key = e.target.value;
            if (key === '') {
              if (node.preset) {
                ctx.updateNode((d) => {
                  const p = d.preset;
                  delete d.preset;
                  d.type = 'xyz';
                  d.id = d.id ?? p;
                  d.url = d.url ?? '';
                  d.label = d.label ?? (ctx.presets[p]?.label ?? p);
                });
              }
              return;
            }
            ctx.updateNode((d) => {
              for (const k of ['type', 'id', 'url', 'attribution', 'subdomains', 'maxZoom']) {
                delete d[k];
              }
              d.preset = key;
            });
          }}
        >
          <option value="" ?selected=${!node.preset}>${t('preset.custom')}</option>
          ${presets.map(
            ([key, preset]) => html`<option value=${key} ?selected=${key === node.preset}>${preset.label ?? key}</option>`
          )}
        </select>
      `);
    }

    case 'icon': {
      return wrap(html`${iconCore(value, onChange, Boolean(descriptor.allowFalse), id)}`);
    }

    case 'iconBy': {
      const property = value?.property ?? '';
      const values = value?.values ?? {};
      const rows = Object.entries(values);

      const commitValues = (entries, opts) => {
        const nextValues = {};
        for (const [k, v] of entries) nextValues[k] = v;
        onChange(
          { property, values: nextValues },
          opts
        );
      };

      const replacement = (entries, key, nextValue) =>
        entries.map(([k, v]) => (k === key ? [k, nextValue] : [k, v]));

      const propertyInput = (inputId, onInput) => html`
        <input
          type="text"
          class="form-control form-control-sm"
          id="${inputId}"
          list="${inputId}-list"
          ?disabled=${disabled}
          .value=${live(property)}
          @input=${onInput}
        />
        ${datalist(inputId + '-list', ctx.properties)}
      `;

      const onProperty = (e) => {
        const raw = e.target.value;
        if (!raw) {
          onChange(undefined, { coalesce: descriptor.key });
        } else if (!value) {
          onChange({ property: raw, values: {} }, { coalesce: descriptor.key });
        } else {
          onChange({ ...value, property: raw }, { coalesce: descriptor.key });
        }
      };

      const addRow = () => {
        let i = 0;
        let name = 'neu';
        while (Object.prototype.hasOwnProperty.call(values, name)) {
          i++;
          name = `neu ${i + 1}`;
        }
        const color = ctx.palette.length
          ? ctx.palette[rows.length % ctx.palette.length]
          : '#2a81cb';
        commitValues([...rows, [name, { type: 'pin', color }]]);
      };

      const addFromData = () => {
        if (!property) return;
        const existing = new Set(Object.keys(values));
        const entries = [...rows];
        let idx = entries.length;
        for (const distinct of ctx.distinctValues(property)) {
          if (!existing.has(distinct)) {
            const color = ctx.palette.length
              ? ctx.palette[idx % ctx.palette.length]
              : '#2a81cb';
            entries.push([distinct, { type: 'pin', color }]);
            existing.add(distinct);
            idx++;
          }
        }
        commitValues(entries);
      };

      return wrap(html`
        <div class="mb-2">
          <label class="form-label small" for="${id}-property">${t('widget.property')}</label>
          ${propertyInput(id + '-property', onProperty)}
        </div>
        ${property
          ? html`
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>${t('widget.value')}</th>
                    <th>${t('widget.label')}</th>
                    <th>${t('widget.color')}</th>
                    <th>${t('widget.glyph')}</th>
                    <th></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  ${rows.map(([key, row]) => {
                    const isPin = row.type === 'pin';
                    return html`
                      <tr>
                        <td>
                          <input
                            type="text"
                            class="form-control form-control-sm"
                            ?disabled=${disabled}
                            .value=${live(key)}
                            @change=${(e) => {
                              const nextKey = e.target.value;
                              if (nextKey && nextKey !== key) {
                                const entries = rows.map(([k, v]) =>
                                  k === key ? [nextKey, v] : [k, v]
                                );
                                commitValues(entries);
                              }
                            }}
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            class="form-control form-control-sm"
                            ?disabled=${disabled}
                            .value=${live(row.label ?? '')}
                            @input=${(e) => {
                              const raw = e.target.value;
                              const next = { ...row };
                              if (raw === '') delete next.label;
                              else next.label = raw;
                              commitValues(replacement(rows, key, next), { coalesce: descriptor.key });
                            }}
                          />
                        </td>
                        <td>
                          <input
                            type="color"
                            class="form-control form-control-color"
                            ?disabled=${disabled || !isPin}
                            .value=${live(isPin && /^#[0-9a-f]{6}$/i.test(row.color ?? '') ? row.color : '#000000')}
                            @change=${(e) => {
                              if (!isPin) return;
                              commitValues(replacement(rows, key, { ...row, color: e.target.value }));
                            }}
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            class="form-control form-control-sm"
                            maxlength="2"
                            ?disabled=${disabled || !isPin}
                            .value=${live(isPin ? row.glyph ?? '' : '')}
                            @input=${(e) => {
                              if (!isPin) return;
                              const raw = e.target.value.slice(0, 2);
                              const next = { ...row };
                              if (raw === '') delete next.glyph;
                              else next.glyph = raw;
                              commitValues(replacement(rows, key, next), { coalesce: descriptor.key });
                            }}
                          />
                        </td>
                        <td>
                          ${unsafeHTML(ctx.iconHtml(row))}
                        </td>
                        <td>
                          <button
                            type="button"
                            class="btn btn-default btn-sm"
                            ?disabled=${disabled}
                            @click=${() => commitValues(rows.filter(([k]) => k !== key))}
                          >
                            ${t('widget.remove')}
                          </button>
                        </td>
                      </tr>
                    `;
                  })}
                </tbody>
              </table>
              <div class="d-flex flex-wrap gap-1 mt-1">
                <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${addRow}>
                  ${t('widget.addRow')}
                </button>
                <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled || !property} @click=${addFromData}>
                  ${t('widget.fromData')}
                </button>
              </div>
            `
          : nothing}
      `);
    }

    case 'colorBy': {
      const property = value?.property ?? '';
      const mode = value?.mode ?? 'categories';
      const palette = (idx) =>
        ctx.palette.length ? ctx.palette[idx % ctx.palette.length] : '#2a81cb';

      const onProperty = (e) => {
        const raw = e.target.value;
        if (!raw) {
          onChange(undefined, { coalesce: descriptor.key });
        } else if (!value) {
          onChange(
            { property: raw, mode: 'categories', categories: [] },
            { coalesce: descriptor.key }
          );
        } else {
          onChange({ ...value, property: raw }, { coalesce: descriptor.key });
        }
      };

      const onMode = (e) => {
        if (!value) return;
        const nextMode = e.target.value;
        const next = { ...value, mode: nextMode };
        if (nextMode === 'categories') {
          next.categories = next.categories ?? [];
          delete next.ranges;
        } else {
          next.ranges = next.ranges ?? [];
          delete next.categories;
        }
        onChange(next);
      };

      const categoryRows = value?.categories ?? [];
      const rangeRows = value?.ranges ?? [];

      const rowLabelInput = (rowValue, onInput) => html`
        <input
          type="text"
          class="form-control form-control-sm"
          ?disabled=${disabled}
          .value=${live(rowValue ?? '')}
          @input=${onInput}
        />
      `;

      const replaceRow = (rows, index, nextRow) =>
        rows.map((row, i) => (i === index ? nextRow : row));

      const addCategory = () => {
        const nextRows = [...categoryRows, { value: '', color: palette(categoryRows.length) }];
        onChange({ ...value, categories: nextRows });
      };

      const addCategoriesFromData = () => {
        const existing = new Set(categoryRows.map((row) => String(row.value)));
        const entries = [...categoryRows];
        let idx = entries.length;
        for (const distinct of ctx.distinctValues(property)) {
          if (!existing.has(String(distinct))) {
            entries.push({ value: String(distinct), color: palette(idx) });
            existing.add(String(distinct));
            idx++;
          }
        }
        onChange({ ...value, categories: entries });
      };

      const addRange = () => {
        const prevMax = rangeRows.length ? rangeRows[rangeRows.length - 1].max : undefined;
        const row = { color: palette(rangeRows.length) };
        if (prevMax !== undefined) row.min = prevMax;
        onChange({ ...value, ranges: [...rangeRows, row] });
      };

      return wrap(html`
        <div class="mb-2">
          <label class="form-label small" for="${id}-property">${t('widget.property')}</label>
          <input
            type="text"
            class="form-control form-control-sm"
            id="${id}-property"
            list="${id}-property-list"
            ?disabled=${disabled}
            .value=${live(property)}
            @input=${onProperty}
          />
          ${datalist(id + '-property-list', ctx.properties)}
        </div>

        ${property
          ? html`
              <div class="mb-2">
                <label class="form-label small" for="${id}-mode">${t('mode.label')}</label>
                <select
                  class="form-select form-select-sm"
                  id="${id}-mode"
                  ?disabled=${disabled}
                  .value=${live(mode)}
                  @change=${onMode}
                >
                  <option value="categories">${t('mode.categories')}</option>
                  <option value="ranges">${t('mode.ranges')}</option>
                </select>
              </div>

              ${mode === 'categories'
                ? html`
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>${t('widget.value')}</th>
                          <th>${t('widget.color')}</th>
                          <th>${t('widget.label')}</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        ${categoryRows.map((row, index) => {
                          const rowValue = String(row.value ?? '');
                          return html`
                            <tr>
                              <td>
                                <input
                                  type="text"
                                  class="form-control form-control-sm"
                                  ?disabled=${disabled}
                                  .value=${live(rowValue)}
                                  @input=${(e) => {
                                    const nextRow = { ...row, value: e.target.value };
                                    onChange({ ...value, categories: replaceRow(categoryRows, index, nextRow) }, { coalesce: descriptor.key });
                                  }}
                                />
                              </td>
                              <td>
                                <input
                                  type="color"
                                  class="form-control form-control-color"
                                  ?disabled=${disabled}
                                  .value=${live(/^#[0-9a-f]{6}$/i.test(row.color ?? '') ? row.color : '#000000')}
                                  @change=${(e) =>
                                    onChange({ ...value, categories: replaceRow(categoryRows, index, { ...row, color: e.target.value }) })}
                                />
                              </td>
                              <td>
                                <input
                                  type="text"
                                  class="form-control form-control-sm"
                                  ?disabled=${disabled}
                                  .value=${live(row.label ?? '')}
                                  @input=${(e) => {
                                    const raw = e.target.value;
                                    const nextRow = { ...row };
                                    if (raw === '') delete nextRow.label;
                                    else nextRow.label = raw;
                                    onChange({ ...value, categories: replaceRow(categoryRows, index, nextRow) }, { coalesce: descriptor.key });
                                  }}
                                />
                              </td>
                              <td>
                                <button
                                  type="button"
                                  class="btn btn-default btn-sm"
                                  ?disabled=${disabled}
                                  @click=${() => onChange({ ...value, categories: categoryRows.filter((_, i) => i !== index) })}
                                >
                                  ${t('widget.remove')}
                                </button>
                              </td>
                            </tr>
                          `;
                        })}
                      </tbody>
                    </table>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                      <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${addCategory}>
                        ${t('widget.addRow')}
                      </button>
                      <button type="button" class="btn btn-default btn-sm" ?disabled=${disabled} @click=${addCategoriesFromData}>
                        ${t('widget.fromData')}
                      </button>
                    </div>
                  `
                : html`
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>${t('widget.min')}</th>
                          <th>${t('widget.max')}</th>
                          <th>${t('widget.color')}</th>
                          <th>${t('widget.label')}</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        ${rangeRows.map((row, index) => html`
                          <tr>
                            <td>
                              <input
                                type="number"
                                class="form-control form-control-sm"
                                ?disabled=${disabled}
                                .value=${live(row.min ?? '')}
                                @input=${(e) => {
                                  const nextRow = { ...row };
                                  const n = numberFromRaw(e.target.value);
                                  if (n === undefined) delete nextRow.min;
                                  else nextRow.min = n;
                                  onChange({ ...value, ranges: replaceRow(rangeRows, index, nextRow) }, { coalesce: descriptor.key });
                                }}
                              />
                            </td>
                            <td>
                              <input
                                type="number"
                                class="form-control form-control-sm"
                                ?disabled=${disabled}
                                .value=${live(row.max ?? '')}
                                @input=${(e) => {
                                  const nextRow = { ...row };
                                  const n = numberFromRaw(e.target.value);
                                  if (n === undefined) delete nextRow.max;
                                  else nextRow.max = n;
                                  onChange({ ...value, ranges: replaceRow(rangeRows, index, nextRow) }, { coalesce: descriptor.key });
                                }}
                              />
                            </td>
                            <td>
                              <input
                                type="color"
                                class="form-control form-control-color"
                                ?disabled=${disabled}
                                .value=${live(/^#[0-9a-f]{6}$/i.test(row.color ?? '') ? row.color : '#000000')}
                                @change=${(e) =>
                                  onChange({ ...value, ranges: replaceRow(rangeRows, index, { ...row, color: e.target.value }) })}
                              />
                            </td>
                            <td>
                              <input
                                type="text"
                                class="form-control form-control-sm"
                                ?disabled=${disabled}
                                .value=${live(row.label ?? '')}
                                @input=${(e) => {
                                  const raw = e.target.value;
                                  const nextRow = { ...row };
                                  if (raw === '') delete nextRow.label;
                                  else nextRow.label = raw;
                                  onChange({ ...value, ranges: replaceRow(rangeRows, index, nextRow) }, { coalesce: descriptor.key });
                                }}
                              />
                            </td>
                            <td>
                              <button
                                type="button"
                                class="btn btn-default btn-sm"
                                ?disabled=${disabled}
                                @click=${() => onChange({ ...value, ranges: rangeRows.filter((_, i) => i !== index) })}
                              >
                                ${t('widget.remove')}
                              </button>
                            </td>
                          </tr>
                        `)}
                      </tbody>
                    </table>
                    <button type="button" class="btn btn-default btn-sm mt-1" ?disabled=${disabled} @click=${addRange}>
                      ${t('widget.addRow')}
                    </button>
                  `}

              <div class="mt-2">
                <label class="form-label small" for="${id}-fallback">${t('widget.fallback')}</label>
                ${colorControl(
                  value.fallback,
                  (raw, coalesce) => {
                    const next = { ...value };
                    if (raw === '') delete next.fallback;
                    else next.fallback = raw;
                    onChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
                  },
                  id + '-fallback'
                )}
              </div>
            `
          : nothing}
      `);
    }

    case 'cluster': {
      const isOn = value !== undefined && value !== false;
      const radiusVal = value && typeof value === 'object' ? value.radius ?? '' : '';
      const disableVal = value && typeof value === 'object' ? value.disableAtZoom ?? '' : '';

      const onSwitch = (e) => {
        if (e.target.checked) {
          if (!isOn) onChange(true);
        } else {
          onChange(undefined);
        }
      };

      const onNumber = (prop, raw) => {
        const base = value && typeof value === 'object' ? value : {};
        const next = { ...base };
        const n = numberFromRaw(raw);
        if (n === undefined) delete next[prop];
        else next[prop] = n;
        if (next.radius === undefined && next.disableAtZoom === undefined) {
          onChange(true, { coalesce: descriptor.key });
        } else {
          onChange(next, { coalesce: descriptor.key });
        }
      };

      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(isOn)}
            @change=${onSwitch}
          />
          <label class="form-check-label" for="${id}">${t('cluster.enable')}</label>
        </div>
        ${isOn
          ? html`
              <div class="d-flex gap-2 mt-2">
                <div>
                  <label class="form-label small" for="${id}-radius">${t('cluster.radius')}</label>
                  <input
                    type="number"
                    class="form-control form-control-sm"
                    id="${id}-radius"
                    placeholder="60"
                    ?disabled=${disabled}
                    .value=${live(radiusVal)}
                    @input=${(e) => onNumber('radius', e.target.value)}
                  />
                </div>
                <div>
                  <label class="form-label small" for="${id}-disable">${t('cluster.disableAtZoom')}</label>
                  <input
                    type="number"
                    class="form-control form-control-sm"
                    id="${id}-disable"
                    ?disabled=${disabled}
                    .value=${live(disableVal)}
                    @input=${(e) => onNumber('disableAtZoom', e.target.value)}
                  />
                </div>
              </div>
            `
          : nothing}
      `);
    }

    case 'filter': {
      const isOn = value !== undefined && value !== false;
      const property = value?.property ?? '';
      const labelVal = value?.label ?? '';

      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(isOn)}
            @change=${(e) => {
              if (e.target.checked) {
                if (!isOn) onChange({ property: '' });
              } else {
                onChange(undefined);
              }
            }}
          />
          <label class="form-check-label" for="${id}">${t('filter.enable')}</label>
        </div>
        ${isOn
          ? html`
              <div class="mt-2">
                <label class="form-label small" for="${id}-property">${t('widget.property')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${id}-property"
                  list="${id}-property-list"
                  ?disabled=${disabled}
                  .value=${live(property)}
                  @input=${(e) => {
                    const next = { ...(value ?? { property: '' }), property: e.target.value };
                    onChange(next, { coalesce: descriptor.key });
                  }}
                />
                ${datalist(id + '-property-list', ctx.properties)}
              </div>
              <div class="mt-2">
                <label class="form-label small" for="${id}-label">${t('filter.label')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${id}-label"
                  ?disabled=${disabled}
                  .value=${live(labelVal)}
                  @input=${(e) => {
                    const raw = e.target.value;
                    const next = { ...(value ?? { property: '' }) };
                    if (raw === '') delete next.label;
                    else next.label = raw;
                    onChange(next, { coalesce: descriptor.key });
                  }}
                />
              </div>
            `
          : nothing}
      `);
    }

    case 'legend': {
      const defaultLegend = descriptor.default ?? false;
      const isOn = value !== false && (value !== undefined || defaultLegend);
      const titleVal = value && typeof value === 'object' && 'title' in value ? value.title : '';

      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(isOn)}
            @change=${(e) => {
              const enabled = e.target.checked;
              if (enabled) {
                if (value === false) {
                  onChange(defaultLegend ? undefined : true);
                } else if (value === undefined && !defaultLegend) {
                  onChange(true);
                }
              } else {
                if (value === undefined && defaultLegend) {
                  onChange(false);
                } else if (value !== false) {
                  onChange(defaultLegend ? false : undefined);
                }
              }
            }}
          />
          <label class="form-check-label" for="${id}">${t('legend.show')}</label>
        </div>
        ${isOn
          ? html`
              <div class="mt-2">
                <label class="form-label small" for="${id}-title">${t('legend.title')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${id}-title"
                  ?disabled=${disabled}
                  .value=${live(titleVal)}
                  @input=${(e) => {
                    const raw = e.target.value;
                    if (raw === '') {
                      onChange(defaultLegend ? undefined : true, { coalesce: descriptor.key });
                    } else {
                      onChange({ title: raw }, { coalesce: descriptor.key });
                    }
                  }}
                />
              </div>
            `
          : nothing}
      `);
    }

    case 'waypoints': {
      const isOn = value !== false;
      const iconVal = value && value !== false && value.icon ? value.icon : undefined;

      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(isOn)}
            @change=${(e) => {
              if (e.target.checked) {
                if (value === false) onChange(undefined);
              } else {
                if (value !== false) onChange(false);
              }
            }}
          />
          <label class="form-check-label" for="${id}">${t('waypoints.show')}</label>
        </div>
        ${isOn
          ? html`<div class="mt-2">${iconCore(iconVal, (next, opts) => {
              if (next === undefined) onChange(undefined, opts);
              else onChange({ icon: next }, opts);
            }, false, id + '-icon')}</div>`
          : nothing}
      `);
    }

    case 'notFalse': {
      return wrap(html`
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="${id}"
            ?disabled=${disabled}
            .checked=${live(value !== false)}
            @change=${(e) => onChange(e.target.checked ? undefined : false)}
          />
        </div>
      `);
    }

    case 'source': {
      const currentKind =
        value?.type === 'addresses'
          ? 'addresses'
          : value?.type === 'geojson'
            ? value.data !== undefined
              ? 'inline'
              : 'geojson'
            : value?.type === 'gpx'
              ? 'gpx'
              : 'inline';

      const onKind = (e) => {
        const kind = e.target.value;
        if (kind === 'inline') {
          onChange({ type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
        } else if (kind === 'geojson') {
          onChange({ type: 'geojson', url: '' });
        } else if (kind === 'gpx') {
          onChange({ type: 'gpx', url: '' });
        } else if (kind === 'addresses') {
          onChange({ type: 'addresses', pids: [] });
        }
      };

      const parseIds = (raw) =>
        raw
          .split(',')
          .map((s) => s.trim())
          .filter((s) => /^\d+$/.test(s))
          .map(Number)
          .filter((n) => Number.isInteger(n) && n > 0);

      return wrap(html`
        <div class="mb-2">
          <label class="form-label small" for="${id}-kind">${t('source.kind')}</label>
          <select class="form-select form-select-sm" id="${id}-kind" ?disabled=${disabled} .value=${live(currentKind)} @change=${onKind}>
            <option value="inline">${t('source.inline')}</option>
            <option value="geojson">${t('source.geojson')}</option>
            <option value="gpx">${t('source.gpx')}</option>
            <option value="addresses">${t('source.addresses')}</option>
          </select>
        </div>

        ${currentKind === 'inline'
          ? html`
              <div class="form-text">
                ${t('source.features').replace('%d', ctx.featureCount ?? 0)}
              </div>
              <button type="button" class="btn btn-default btn-sm mt-1" ?disabled=${disabled} @click=${() => ctx.startDrawing()}>
                ${t('source.draw')}
              </button>
            `
          : nothing}

        ${currentKind === 'geojson' || currentKind === 'gpx'
          ? html`
              <div class="mb-2">
                <label class="form-label small" for="${id}-url">${t('widget.url')}</label>
                ${fileControl(
                  value.url ?? '',
                  (raw, coalesce) => {
                    const next = { ...value, url: raw };
                    onChange(next, coalesce ? { coalesce: descriptor.key } : undefined);
                  },
                  id + '-url',
                  descriptor.allowed
                )}
              </div>
            `
          : nothing}

        ${currentKind === 'addresses'
          ? html`
              <div class="mb-2">
                <label class="form-label small" for="${id}-pids">${t('source.pids')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${id}-pids"
                  ?disabled=${disabled}
                  .value=${live((value.pids ?? []).join(', '))}
                  @change=${(e) => {
                    const next = { ...value, pids: parseIds(e.target.value) };
                    onChange(next, { coalesce: descriptor.key });
                  }}
                />
              </div>
              <div class="mb-2">
                <label class="form-label small" for="${id}-categories">${t('source.categories')}</label>
                <input
                  type="text"
                  class="form-control form-control-sm"
                  id="${id}-categories"
                  ?disabled=${disabled}
                  .value=${live((value.categories ?? []).join(', '))}
                  @change=${(e) => {
                    const categories = parseIds(e.target.value);
                    const next = { ...value };
                    if (categories.length) next.categories = categories;
                    else delete next.categories;
                    onChange(next, { coalesce: descriptor.key });
                  }}
                />
              </div>
            `
          : nothing}
      `);
    }

    default:
      return wrap(html`<div class="form-text">${descriptor.type}</div>`);
  }
}
