import { LitElement, html, nothing } from 'lit';
import { call } from '@wapplersystems/mapmagic/backend/api.js';
import labels from '~labels/map_magic.editor';

/** Wait this long after the last change before asking the server to compile again. */
const REFRESH_DELAY = 400;

/** Geoman tools per layer type: what can be drawn into it. */
const DRAW_TOOLS = {
  markers: { drawMarker: true },
  areas: { drawPolygon: true, drawRectangle: true },
  route: { drawPolyline: true },
};

/**
 * Live preview. The definition is compiled on the server (EXT:/FAL paths,
 * presets and records only resolve there) and rendered with the frontend
 * renderer (global MapMagic), so the preview shows exactly what the website
 * will show.
 *
 * Drawing: for one layer with inline data the rendered layer is swapped for
 * an editable copy (Leaflet-Geoman). Every edit is written back to the model
 * as GeoJSON; the preview does not re-render while drawing, otherwise each
 * stroke would rebuild the map under the pointer.
 *
 * Events: "feature-select" {layerIndex, featureIndex}, "drawing-change" {layerIndex|null}.
 */
export class MapMagicPreview extends LitElement {
  static properties = {
    model: { attribute: false },
    messages: { attribute: false },
    _warnings: { state: true },
    _error: { state: true },
    _drawing: { state: true },
  };

  #handle = null;
  #timer = 0;
  #requestNo = 0;
  #lastCompiledJson = '';
  #compiled = null;
  #dataCache = new Map();
  #editable = null;
  /** True until the user pans or zooms; while true, resizes re-fit the start view. */
  #autoView = true;
  #resizeObserver = new ResizeObserver(() => {
    if (!this.#handle) return;
    if (this.#autoView) this.#handle.resetView();
    else this.#handle.map.invalidateSize();
  });
  #onModelChange = () => this.#scheduleRefresh();

  constructor() {
    super();
    this._warnings = [];
    this._error = '';
    this._drawing = null;
  }

  createRenderRoot() {
    // Leaflet and its plugins style the map through global CSS.
    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    this.model.addEventListener('change', this.#onModelChange);
    this.#scheduleRefresh(0);
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    this.model.removeEventListener('change', this.#onModelChange);
    window.clearTimeout(this.#timer);
    this.#resizeObserver.disconnect();
    this.#handle?.destroy();
  }

  firstUpdated() {
    this.#resizeObserver.observe(this.querySelector('.mapmagic-editor__map'));
  }

  render() {
    return html`
      ${this._drawing !== null
        ? html`<div class="mapmagic-editor__drawbar alert alert-info d-flex align-items-center gap-2 mb-2">
            <span class="flex-grow-1">${labels.get('draw.active')}</span>
            <button type="button" class="btn btn-primary btn-sm" @click=${() => this.stopDrawing()}>${labels.get('draw.done')}</button>
          </div>`
        : nothing}
      <div class="mapmagic-editor__map"></div>
      ${this._error ? html`<div class="alert alert-danger mt-2 mb-0">${this._error}</div>` : nothing}
      ${this._warnings.map(
        (w) => html`<div class="alert alert-warning mt-2 mb-0">${labels.get('preview.layerDropped', [w.layer, w.message])}</div>`,
      )}
    `;
  }

  /** Current view in format terms, for "use current view". */
  getView() {
    return this.#handle?.getView() ?? null;
  }

  /** Features of a layer as the renderer sees them (loaded from URL if needed), for property suggestions. */
  async layerData(layerId) {
    const layer = this.#compiled?.layers?.find((l) => l.id === layerId);
    if (!layer) return null;
    const key = JSON.stringify(layer.source);
    if (!this.#dataCache.has(key)) {
      this.#dataCache.set(key, window.MapMagic.loadSource(layer.source).catch(() => null));
    }
    return this.#dataCache.get(key);
  }

  startDrawing(layerIndex) {
    const layer = this.model.definition.layers[layerIndex];
    if (!this.#handle || !layer || !('data' in (layer.source ?? {}))) return;
    this.stopDrawing();
    this._drawing = layerIndex;
    this.#enterDrawMode(layerIndex, layer);
    this.dispatchEvent(new CustomEvent('drawing-change', { detail: { layerIndex } }));
  }

  stopDrawing() {
    if (this._drawing === null) return;
    const map = this.#handle?.map;
    if (map) {
      map.pm.removeControls();
      map.pm.disableGlobalEditMode();
      map.pm.disableGlobalDragMode();
      map.pm.disableGlobalRemovalMode();
      map.pm.disableDraw();
      map.off('pm:create');
      this.#editable?.remove();
    }
    this.#editable = null;
    this._drawing = null;
    this.dispatchEvent(new CustomEvent('drawing-change', { detail: { layerIndex: null } }));
    this.#scheduleRefresh(0);
  }

  #scheduleRefresh(delay = REFRESH_DELAY) {
    if (this._drawing !== null) return;
    window.clearTimeout(this.#timer);
    this.#timer = window.setTimeout(() => this.#refresh(), delay);
  }

  async #refresh() {
    const json = JSON.stringify(this.model.definition);
    if (json === this.#lastCompiledJson) return;
    const requestNo = ++this.#requestNo;
    let result;
    try {
      result = await call('mapmagic_preview', { definition: this.model.definition });
    } catch (error) {
      if (requestNo === this.#requestNo) this._error = error.message;
      return;
    }
    // A slower, older answer must not overwrite a newer preview.
    if (requestNo !== this.#requestNo) return;
    this.#lastCompiledJson = json;
    this._error = '';
    this._warnings = result.warnings ?? [];
    await this.#render(result.map);
  }

  async #render(compiled) {
    const container = this.querySelector('.mapmagic-editor__map');
    const previous = this.#handle;
    // Keep the visitor's pan/zoom across re-renders unless the map kind or the
    // basemap changed (an image map's coordinates mean nothing on tiles).
    const sameSpace =
      previous && previous.kind === (compiled.kind ?? 'geo') && JSON.stringify(previous.def.basemaps) === JSON.stringify(compiled.basemaps);
    const view = sameSpace ? { center: previous.map.getCenter(), zoom: previous.map.getZoom() } : null;
    previous?.destroy();
    this.#handle = null;

    this.#compiled = compiled;
    const def = {
      ...compiled,
      height: '100%',
      // The preview is a working surface, not an article: zoom without Ctrl.
      interaction: { ...(compiled.interaction ?? {}), scrollWheelZoom: 'always' },
    };
    try {
      this.#handle = await window.MapMagic.createMap(container, def, { messages: this.messages });
      this._error = '';
    } catch (error) {
      this._error = error.message;
      return;
    }
    if (view) {
      this.#handle.map.setView(view.center, view.zoom, { animate: false });
    } else {
      this.#autoView = true;
    }
    // Only gestures count as "the user chose a view", not the programmatic fit.
    this.#handle.map.on('dragstart', () => (this.#autoView = false));
    this.#handle.map.getContainer().addEventListener('wheel', () => (this.#autoView = false), { passive: true });
    this.#handle.map.on('zoomend', (e) => e.originalEvent && (this.#autoView = false));
    this.dispatchEvent(new CustomEvent('rendered'));
  }

  #enterDrawMode(layerIndex, layer) {
    const L = window.L;
    const handle = this.#handle;
    const map = handle.map;
    // The rendered (styled, clustered) layer is replaced by a plain editable one.
    const rendered = handle.layers.get(layer.id);
    if (rendered) map.removeLayer(rendered.leafletLayer);

    const features = layer.source.data?.features ?? [];
    this.#editable = L.featureGroup().addTo(map);
    features.forEach((feature, index) => {
      const leafletLayer = this.#toEditableLayer(feature);
      if (!leafletLayer) return;
      leafletLayer.mmProperties = feature.properties ?? {};
      leafletLayer.mmIndex = index;
      leafletLayer.on('click', () => this.#selectFeature(layerIndex, leafletLayer));
      this.#editable.addLayer(leafletLayer);
    });

    map.pm.setGlobalOptions({ layerGroup: this.#editable, snappable: true });
    map.pm.addControls({
      position: 'topleft',
      drawMarker: false,
      drawCircleMarker: false,
      drawPolyline: false,
      drawRectangle: false,
      drawPolygon: false,
      drawCircle: false,
      drawText: false,
      cutPolygon: false,
      rotateMode: false,
      ...DRAW_TOOLS[layer.type],
      editMode: true,
      dragMode: true,
      removalMode: true,
    });

    map.on('pm:create', ({ layer: created }) => {
      created.mmProperties = {};
      created.on('click', () => this.#selectFeature(layerIndex, created));
      this.#editable.addLayer(created);
      this.#watch(created, layerIndex);
      this.#sync(layerIndex);
      this.#selectFeature(layerIndex, created);
    });
    this.#editable.eachLayer((l) => this.#watch(l, layerIndex));
    if (this.#editable.getLayers().length > 0 && !this.model.definition.view?.center) {
      map.fitBounds(this.#editable.getBounds(), { padding: [30, 30], maxZoom: map.getZoom() });
    }
  }

  #watch(leafletLayer, layerIndex) {
    const sync = () => this.#sync(layerIndex);
    leafletLayer.on('pm:edit pm:dragend pm:markerdragend', sync);
    leafletLayer.on('pm:remove', () => {
      this.#editable.removeLayer(leafletLayer);
      sync();
    });
  }

  /** Writes the editable layer back to the model as the layer's inline features. */
  #sync(layerIndex) {
    const features = [];
    this.#editable.eachLayer((leafletLayer) => {
      const geometry = this.#toGeometry(leafletLayer);
      if (!geometry) return;
      leafletLayer.mmIndex = features.length;
      features.push({ type: 'Feature', properties: leafletLayer.mmProperties ?? {}, geometry });
    });
    this.model.set(['layers', layerIndex, 'source', 'data'], { type: 'FeatureCollection', features });
  }

  #selectFeature(layerIndex, leafletLayer) {
    this.dispatchEvent(
      new CustomEvent('feature-select', { detail: { layerIndex, featureIndex: leafletLayer.mmIndex ?? null } }),
    );
  }

  /** Properties edited in the inspector go to the editable layer too, so the next sync keeps them. */
  setFeatureProperties(featureIndex, properties) {
    this.#editable?.eachLayer((l) => {
      if (l.mmIndex === featureIndex) l.mmProperties = properties;
    });
  }

  #toEditableLayer(feature) {
    const L = window.L;
    const g = feature.geometry;
    if (!g) return null;
    const ll = (p) => this.#handle.toLatLng(p);
    switch (g.type) {
      case 'Point':
        return L.marker(ll(g.coordinates));
      case 'LineString':
        return L.polyline(g.coordinates.map(ll));
      case 'Polygon':
        return L.polygon(g.coordinates.map((ring) => openRing(ring).map(ll)));
      case 'MultiPolygon':
        return L.polygon(g.coordinates.map((poly) => poly.map((ring) => openRing(ring).map(ll))));
      default:
        // MultiPoint / MultiLineString are not editable by Geoman; they stay as they are.
        return null;
    }
  }

  #toGeometry(leafletLayer) {
    const L = window.L;
    const pos = (latLng) => this.#handle.toPosition(latLng);
    if (leafletLayer instanceof L.Marker) {
      return { type: 'Point', coordinates: pos(leafletLayer.getLatLng()) };
    }
    if (leafletLayer instanceof L.Polygon) {
      const rings = leafletLayer.getLatLngs();
      // Leaflet nests one level deeper for multi-polygons.
      if (Array.isArray(rings[0]?.[0])) {
        return { type: 'MultiPolygon', coordinates: rings.map((poly) => poly.map((ring) => closeRing(ring.map(pos)))) };
      }
      return { type: 'Polygon', coordinates: rings.map((ring) => closeRing(ring.map(pos))) };
    }
    if (leafletLayer instanceof L.Polyline) {
      return { type: 'LineString', coordinates: leafletLayer.getLatLngs().map(pos) };
    }
    return null;
  }
}

/** GeoJSON rings repeat the first point at the end; Leaflet rings do not. */
function openRing(ring) {
  const first = ring[0];
  const last = ring[ring.length - 1];
  return ring.length > 1 && first[0] === last[0] && first[1] === last[1] ? ring.slice(0, -1) : ring;
}

function closeRing(ring) {
  if (ring.length === 0) return ring;
  const first = ring[0];
  const last = ring[ring.length - 1];
  return first[0] === last[0] && first[1] === last[1] ? ring : [...ring, first];
}

customElements.define('mapmagic-preview', MapMagicPreview);
