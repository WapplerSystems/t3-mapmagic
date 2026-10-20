/**
 * What the inspector shows for each node of a map definition. Groups of
 * field descriptors; each descriptor names its value by a dotted key relative
 * to the node (e.g. "style.color") and a widget type implemented in fields.js.
 *
 * Defaults mirror MapMagicJs/src/schema.ts: a field left at its default is
 * removed from the definition, so files only contain what was chosen.
 *
 * Descriptor:
 *   key        dotted path relative to the node
 *   type       widget, see fields.js
 *   label      label key in map_magic.editor
 *   help       optional help label key
 *   default    renderer default (checkbox/select/number)
 *   …          widget-specific options (options, min, max, step, allowed, allowFalse, placeholder)
 *   when       optional (node) => boolean, field is hidden otherwise
 */

const IMAGE_TYPES = 'svg,png,jpg,jpeg,webp,gif';
const DATA_TYPES = 'geojson,json,gpx';

const commonLayerFields = [
  { key: 'label', type: 'text', label: 'field.label' },
  { key: 'id', type: 'text', label: 'field.id', help: 'help.id', required: true },
  { key: 'visible', type: 'checkbox', label: 'field.visible', default: true },
  { key: 'listed', type: 'checkbox', label: 'field.listed', default: true },
];

const sourceGroup = (allowed) => ({
  group: 'group.source',
  // No field label: the group heading already says "Data".
  fields: [{ key: 'source', type: 'source', allowed }],
});

const infoGroup = {
  group: 'group.info',
  fields: [
    { key: 'tooltip', type: 'template', label: 'field.tooltip', help: 'help.template', rows: 1 },
    { key: 'popup', type: 'template', label: 'field.popup', help: 'help.template', rows: 4 },
    { key: 'attribution', type: 'text', label: 'field.attribution' },
  ],
};

const lineStyleFields = [
  { key: 'style.color', type: 'color', label: 'field.lineColor' },
  { key: 'style.weight', type: 'number', label: 'field.lineWeight', min: 0, max: 20, step: 1 },
  { key: 'style.opacity', type: 'number', label: 'field.lineOpacity', min: 0, max: 1, step: 0.05 },
  { key: 'style.dashArray', type: 'text', label: 'field.dashArray', placeholder: '6 4' },
];

export const nodeSchemas = {
  map: [
    {
      group: 'group.general',
      fields: [
        { key: 'label', type: 'text', label: 'field.mapLabel', required: true },
        { key: 'height', type: 'text', label: 'field.height', placeholder: '500px' },
      ],
    },
    {
      group: 'group.view',
      fields: [
        { key: 'view.center', type: 'center', label: 'field.center' },
        { key: 'view.zoom', type: 'number', label: 'field.zoom', min: -5, max: 22, step: 0.25 },
        { key: 'view.minZoom', type: 'number', label: 'field.minZoom', min: -5, max: 22, step: 1 },
        { key: 'view.maxZoom', type: 'number', label: 'field.maxZoom', min: -5, max: 22, step: 1 },
        { key: 'view.padding', type: 'number', label: 'field.padding', min: 0, max: 200, step: 1, default: 20 },
      ],
    },
    {
      group: 'group.controls',
      fields: [
        { key: 'controls.zoom', type: 'checkbox', label: 'field.controlZoom', default: true },
        {
          key: 'controls.layers',
          type: 'select',
          label: 'field.controlLayers',
          default: 'auto',
          options: [
            { value: 'auto', label: 'option.auto' },
            { value: true, label: 'option.always' },
            { value: false, label: 'option.never' },
          ],
        },
        { key: 'controls.scale', type: 'checkbox', label: 'field.controlScale', default: false, when: (map) => map.kind !== 'image' },
        { key: 'controls.fullscreen', type: 'checkbox', label: 'field.controlFullscreen', default: false },
        { key: 'controls.legend', type: 'checkbox', label: 'field.controlLegend', default: true },
        {
          key: 'interaction.scrollWheelZoom',
          type: 'select',
          label: 'field.scrollWheelZoom',
          default: 'ctrl',
          options: [
            { value: 'ctrl', label: 'option.scrollCtrl' },
            { value: 'always', label: 'option.always' },
            { value: 'never', label: 'option.never' },
          ],
        },
        { key: 'interaction.dragging', type: 'checkbox', label: 'field.dragging', default: true },
      ],
    },
  ],

  'basemap:xyz': [
    {
      group: 'group.basemap',
      fields: [
        { key: 'preset', type: 'preset', label: 'field.preset' },
        { key: 'label', type: 'text', label: 'field.label' },
        { key: 'id', type: 'text', label: 'field.id', help: 'help.id', when: (b) => !b.preset },
        { key: 'url', type: 'text', label: 'field.tileUrl', placeholder: 'https://tile.example.org/{z}/{x}/{y}.png', when: (b) => !b.preset },
        { key: 'attribution', type: 'text', label: 'field.attribution', when: (b) => !b.preset },
        { key: 'subdomains', type: 'text', label: 'field.subdomains', placeholder: 'abc', when: (b) => !b.preset },
        { key: 'maxZoom', type: 'number', label: 'field.maxZoom', min: 0, max: 22, step: 1, when: (b) => !b.preset },
      ],
    },
  ],

  'basemap:image': [
    {
      group: 'group.basemap',
      fields: [
        { key: 'label', type: 'text', label: 'field.label' },
        { key: 'url', type: 'file', label: 'field.image', allowed: IMAGE_TYPES, help: 'help.image' },
        { key: 'width', type: 'number', label: 'field.imageWidth', min: 1, step: 1 },
        { key: 'height', type: 'number', label: 'field.imageHeight', min: 1, step: 1 },
        { key: 'attribution', type: 'text', label: 'field.attribution' },
      ],
    },
  ],

  'layer:markers': [
    { group: 'group.general', fields: commonLayerFields },
    sourceGroup(DATA_TYPES),
    {
      group: 'group.appearance',
      fields: [
        { key: 'icon', type: 'icon', label: 'field.icon' },
        { key: 'iconBy', type: 'iconBy', label: 'field.iconBy', help: 'help.iconBy' },
        { key: 'cluster', type: 'cluster', label: 'field.cluster' },
        { key: 'legend', type: 'legend', label: 'field.legend', when: (layer) => Boolean(layer.iconBy) },
      ],
    },
    { group: 'group.filter', fields: [{ key: 'filter', type: 'filter', label: 'field.filter', help: 'help.filter' }] },
    infoGroup,
  ],

  'layer:areas': [
    { group: 'group.general', fields: commonLayerFields },
    sourceGroup(DATA_TYPES),
    {
      group: 'group.appearance',
      fields: [
        ...lineStyleFields,
        { key: 'style.fillColor', type: 'color', label: 'field.fillColor' },
        { key: 'style.fillOpacity', type: 'number', label: 'field.fillOpacity', min: 0, max: 1, step: 0.05 },
        { key: 'hoverStyle', type: 'notFalse', label: 'field.hoverHighlight' },
        { key: 'colorBy', type: 'colorBy', label: 'field.colorBy', help: 'help.colorBy' },
        { key: 'legend', type: 'legend', label: 'field.legend', default: true, when: (layer) => Boolean(layer.colorBy) },
      ],
    },
    infoGroup,
  ],

  'layer:route': [
    { group: 'group.general', fields: commonLayerFields },
    sourceGroup(DATA_TYPES),
    {
      group: 'group.appearance',
      fields: [
        ...lineStyleFields,
        { key: 'startIcon', type: 'icon', label: 'field.startIcon', allowFalse: true },
        { key: 'endIcon', type: 'icon', label: 'field.endIcon', allowFalse: true },
        { key: 'waypoints', type: 'waypoints', label: 'field.waypoints' },
        { key: 'elevationProfile', type: 'checkbox', label: 'field.elevationProfile', default: false, help: 'help.elevationProfile' },
      ],
    },
    infoGroup,
  ],
};

/** Schema key of the node at the current selection. */
export function schemaKeyFor(selection, node) {
  if (selection.kind === 'map') return 'map';
  if (selection.kind === 'basemap') return node?.type === 'image' ? 'basemap:image' : 'basemap:xyz';
  return `layer:${node?.type ?? 'markers'}`;
}

/** New layer of a type, as added by the "+ layer" menu. Inline data, so drawing works right away. */
export function newLayer(type, id) {
  const layer = {
    type,
    id,
    label: '',
    source: { type: 'geojson', data: { type: 'FeatureCollection', features: [] } },
  };
  if (type === 'markers') layer.tooltip = '{name}';
  return layer;
}

/** Layer types and the label/icon of their "+" menu entry and structure row. */
export const layerTypes = [
  { type: 'markers', label: 'layer.markers', icon: 'actions-marker' },
  { type: 'areas', label: 'layer.areas', icon: 'actions-circle-half' },
  { type: 'route', label: 'layer.route', icon: 'actions-chevron-double-end' },
];
