/**
 * Editor state: the map definition as it will be saved (file format, see
 * MapMagicJs/src/schema.ts), the current selection, undo/redo.
 *
 * All changes go through set()/update(); they replace the definition with a
 * new object (structuredClone + mutation), so components can compare by
 * identity and every step can be undone. Listeners get a "change" event.
 */

const UNDO_LIMIT = 100;

/** Path step of a definition path: object key or array index. */
/** @typedef {string|number} PathStep */

/** @typedef {{kind: 'map'}|{kind: 'basemap', index: number}|{kind: 'layer', index: number}} Selection */

export class EditorModel extends EventTarget {
  #definition;
  #savedJson;
  #undo = [];
  #redo = [];
  /** @type {Selection} */
  #selection = { kind: 'map' };

  constructor(definition) {
    super();
    this.#definition = normalize(definition);
    this.#savedJson = JSON.stringify(this.#definition);
  }

  get definition() {
    return this.#definition;
  }

  get selection() {
    return this.#selection;
  }

  get dirty() {
    return JSON.stringify(this.#definition) !== this.#savedJson;
  }

  get canUndo() {
    return this.#undo.length > 0;
  }

  get canRedo() {
    return this.#redo.length > 0;
  }

  /** @param {PathStep[]} path */
  get(path) {
    return path.reduce((value, step) => (value == null ? undefined : value[step]), this.#definition);
  }

  /**
   * Sets a value; `undefined` removes the key (or array element), so optional
   * fields fall back to their renderer default instead of being stored.
   *
   * @param {PathStep[]} path
   * @param {*} value
   * @param {{coalesce?: string}} options coalesce: consecutive changes with the
   *   same key form one undo step (typing in a text field)
   */
  set(path, value, options = {}) {
    this.update((draft) => {
      const parent = path.slice(0, -1).reduce((node, step, i) => {
        if (node[step] == null || typeof node[step] !== 'object') {
          node[step] = typeof path[i + 1] === 'number' ? [] : {};
        }
        return node[step];
      }, draft);
      const last = path[path.length - 1];
      if (value === undefined) {
        Array.isArray(parent) ? parent.splice(Number(last), 1) : delete parent[last];
      } else {
        parent[last] = value;
      }
      pruneEmpty(draft, path.slice(0, -1));
    }, options);
  }

  /**
   * @param {(draft: object) => void} mutate
   * @param {{coalesce?: string}} options
   */
  update(mutate, options = {}) {
    const draft = structuredClone(this.#definition);
    mutate(draft);
    const previous = this.#definition;
    if (JSON.stringify(draft) === JSON.stringify(previous)) {
      return;
    }
    const top = this.#undo[this.#undo.length - 1];
    if (!(options.coalesce && top && top.coalesce === options.coalesce)) {
      this.#undo.push({ definition: previous, selection: this.#selection, coalesce: options.coalesce });
      if (this.#undo.length > UNDO_LIMIT) this.#undo.shift();
    }
    this.#redo = [];
    this.#definition = draft;
    this.#emit();
  }

  /** Replaces the whole definition, e.g. after editing the YAML. One undo step. */
  replace(definition) {
    this.update((draft) => {
      for (const key of Object.keys(draft)) delete draft[key];
      Object.assign(draft, normalize(definition));
    });
    this.#clampSelection();
  }

  undo() {
    const entry = this.#undo.pop();
    if (!entry) return;
    this.#redo.push({ definition: this.#definition, selection: this.#selection });
    this.#definition = entry.definition;
    this.#selection = entry.selection;
    this.#clampSelection();
    this.#emit();
  }

  redo() {
    const entry = this.#redo.pop();
    if (!entry) return;
    this.#undo.push({ definition: this.#definition, selection: this.#selection });
    this.#definition = entry.definition;
    this.#selection = entry.selection;
    this.#clampSelection();
    this.#emit();
  }

  /** @param {Selection} selection */
  select(selection) {
    this.#selection = selection;
    this.#clampSelection();
    this.#emit();
  }

  markSaved() {
    this.#savedJson = JSON.stringify(this.#definition);
    this.#emit();
  }

  /** Path of the selected node, e.g. ['layers', 2]; [] for the map itself. */
  get selectionPath() {
    const s = this.#selection;
    return s.kind === 'map' ? [] : [s.kind === 'layer' ? 'layers' : 'basemaps', s.index];
  }

  /** All ids in use (basemaps and layers share one namespace in the renderer). */
  get ids() {
    return [...(this.#definition.basemaps ?? []), ...(this.#definition.layers ?? [])]
      .map((item) => item?.id)
      .filter(Boolean);
  }

  /** "markers" → "markers", "markers-2", … */
  uniqueId(base) {
    const ids = new Set(this.ids);
    if (!ids.has(base)) return base;
    let i = 2;
    while (ids.has(`${base}-${i}`)) i++;
    return `${base}-${i}`;
  }

  #clampSelection() {
    const s = this.#selection;
    if (s.kind === 'map') return;
    const list = this.#definition[s.kind === 'layer' ? 'layers' : 'basemaps'] ?? [];
    if (s.index >= list.length) {
      this.#selection = list.length > 0 ? { kind: s.kind, index: list.length - 1 } : { kind: 'map' };
    }
  }

  #emit() {
    this.dispatchEvent(new Event('change'));
  }
}

/**
 * Lists the editor relies on are always arrays. PHP turns empty YAML
 * mappings and lists alike into [], so `layers: {}` can come back as [].
 */
function normalize(definition) {
  const copy = structuredClone(definition ?? {});
  if (!Array.isArray(copy.basemaps)) copy.basemaps = Object.values(copy.basemaps ?? {});
  if (!Array.isArray(copy.layers)) copy.layers = Object.values(copy.layers ?? {});
  return copy;
}

/**
 * Removes objects that became empty by deleting their last key, walking up
 * from `path` (e.g. `style: {}` after clearing the last style field). Lists
 * are kept: an empty `layers: []` is meaningful.
 */
function pruneEmpty(root, path) {
  for (let depth = path.length; depth > 0; depth--) {
    const parentPath = path.slice(0, depth - 1);
    const parent = parentPath.reduce((node, step) => node?.[step], root);
    const key = path[depth - 1];
    const node = parent?.[key];
    const isEmptyObject = node && typeof node === 'object' && !Array.isArray(node) && Object.keys(node).length === 0;
    if (!isEmptyObject || Array.isArray(parent)) return;
    delete parent[key];
  }
}
