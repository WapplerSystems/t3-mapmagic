(function(global2, factory) {
  typeof exports === "object" && typeof module !== "undefined" ? factory(exports, require("leaflet")) : typeof define === "function" && define.amd ? define(["exports", "leaflet"], factory) : (global2 = typeof globalThis !== "undefined" ? globalThis : global2 || self, factory(global2.MapMagic = {}, global2.L));
})(this, function(exports2, L$1) {
  "use strict";var __defProp = Object.defineProperty;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __publicField = (obj, key, value) => __defNormalProp(obj, typeof key !== "symbol" ? key + "" : key, value);

  const scope = globalThis;
  scope.L ?? (scope.L = L$1);
  var commonjsGlobal = typeof globalThis !== "undefined" ? globalThis : typeof window !== "undefined" ? window : typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : {};
  var leaflet_markerclusterSrc = { exports: {} };
  (function(module2, exports3) {
    (function(global2, factory) {
      factory(exports3);
    })(commonjsGlobal, function(exports4) {
      var MarkerClusterGroup = L.MarkerClusterGroup = L.FeatureGroup.extend({
        options: {
          maxClusterRadius: 80,
          //A cluster will cover at most this many pixels from its center
          iconCreateFunction: null,
          clusterPane: L.Marker.prototype.options.pane,
          spiderfyOnEveryZoom: false,
          spiderfyOnMaxZoom: true,
          showCoverageOnHover: true,
          zoomToBoundsOnClick: true,
          singleMarkerMode: false,
          disableClusteringAtZoom: null,
          // Setting this to false prevents the removal of any clusters outside of the viewpoint, which
          // is the default behaviour for performance reasons.
          removeOutsideVisibleBounds: true,
          // Set to false to disable all animations (zoom and spiderfy).
          // If false, option animateAddingMarkers below has no effect.
          // If L.DomUtil.TRANSITION is falsy, this option has no effect.
          animate: true,
          //Whether to animate adding markers after adding the MarkerClusterGroup to the map
          // If you are adding individual markers set to true, if adding bulk markers leave false for massive performance gains.
          animateAddingMarkers: false,
          // Make it possible to provide custom function to calculate spiderfy shape positions
          spiderfyShapePositions: null,
          //Increase to increase the distance away that spiderfied markers appear from the center
          spiderfyDistanceMultiplier: 1,
          // Make it possible to specify a polyline options on a spider leg
          spiderLegPolylineOptions: { weight: 1.5, color: "#222", opacity: 0.5 },
          // When bulk adding layers, adds markers in chunks. Means addLayers may not add all the layers in the call, others will be loaded during setTimeouts
          chunkedLoading: false,
          chunkInterval: 200,
          // process markers for a maximum of ~ n milliseconds (then trigger the chunkProgress callback)
          chunkDelay: 50,
          // at the end of each interval, give n milliseconds back to system/browser
          chunkProgress: null,
          // progress callback: function(processed, total, elapsed) (e.g. for a progress indicator)
          //Options to pass to the L.Polygon constructor
          polygonOptions: {}
        },
        initialize: function(options) {
          L.Util.setOptions(this, options);
          if (!this.options.iconCreateFunction) {
            this.options.iconCreateFunction = this._defaultIconCreateFunction;
          }
          this._featureGroup = L.featureGroup();
          this._featureGroup.addEventParent(this);
          this._nonPointGroup = L.featureGroup();
          this._nonPointGroup.addEventParent(this);
          this._inZoomAnimation = 0;
          this._needsClustering = [];
          this._needsRemoving = [];
          this._currentShownBounds = null;
          this._queue = [];
          this._childMarkerEventHandlers = {
            "dragstart": this._childMarkerDragStart,
            "move": this._childMarkerMoved,
            "dragend": this._childMarkerDragEnd
          };
          var animate = L.DomUtil.TRANSITION && this.options.animate;
          L.extend(this, animate ? this._withAnimation : this._noAnimation);
          this._markerCluster = animate ? L.MarkerCluster : L.MarkerClusterNonAnimated;
        },
        addLayer: function(layer) {
          if (layer instanceof L.LayerGroup) {
            return this.addLayers([layer]);
          }
          if (!layer.getLatLng) {
            this._nonPointGroup.addLayer(layer);
            this.fire("layeradd", { layer });
            return this;
          }
          if (!this._map) {
            this._needsClustering.push(layer);
            this.fire("layeradd", { layer });
            return this;
          }
          if (this.hasLayer(layer)) {
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
          }
          this._addLayer(layer, this._maxZoom);
          this.fire("layeradd", { layer });
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          var visibleLayer = layer, currentZoom = this._zoom;
          if (layer.__parent) {
            while (visibleLayer.__parent._zoom >= currentZoom) {
              visibleLayer = visibleLayer.__parent;
            }
          }
          if (this._currentShownBounds.contains(visibleLayer.getLatLng())) {
            if (this.options.animateAddingMarkers) {
              this._animationAddLayer(layer, visibleLayer);
            } else {
              this._animationAddLayerNonAnimated(layer, visibleLayer);
            }
          }
          return this;
        },
        removeLayer: function(layer) {
          if (layer instanceof L.LayerGroup) {
            return this.removeLayers([layer]);
          }
          if (!layer.getLatLng) {
            this._nonPointGroup.removeLayer(layer);
            this.fire("layerremove", { layer });
            return this;
          }
          if (!this._map) {
            if (!this._arraySplice(this._needsClustering, layer) && this.hasLayer(layer)) {
              this._needsRemoving.push({ layer, latlng: layer._latlng });
            }
            this.fire("layerremove", { layer });
            return this;
          }
          if (!layer.__parent) {
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
            this._unspiderfyLayer(layer);
          }
          this._removeLayer(layer, true);
          this.fire("layerremove", { layer });
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          layer.off(this._childMarkerEventHandlers, this);
          if (this._featureGroup.hasLayer(layer)) {
            this._featureGroup.removeLayer(layer);
            if (layer.clusterShow) {
              layer.clusterShow();
            }
          }
          return this;
        },
        //Takes an array of markers and adds them in bulk
        addLayers: function(layersArray, skipLayerAddEvent) {
          if (!L.Util.isArray(layersArray)) {
            return this.addLayer(layersArray);
          }
          var fg = this._featureGroup, npg = this._nonPointGroup, chunked = this.options.chunkedLoading, chunkInterval = this.options.chunkInterval, chunkProgress = this.options.chunkProgress, l = layersArray.length, offset = 0, originalArray = true, m;
          if (this._map) {
            var started = (/* @__PURE__ */ new Date()).getTime();
            var process = L.bind(function() {
              var start = (/* @__PURE__ */ new Date()).getTime();
              if (this._map && this._unspiderfy) {
                this._unspiderfy();
              }
              for (; offset < l; offset++) {
                if (chunked && offset % 200 === 0) {
                  var elapsed = (/* @__PURE__ */ new Date()).getTime() - start;
                  if (elapsed > chunkInterval) {
                    break;
                  }
                }
                m = layersArray[offset];
                if (m instanceof L.LayerGroup) {
                  if (originalArray) {
                    layersArray = layersArray.slice();
                    originalArray = false;
                  }
                  this._extractNonGroupLayers(m, layersArray);
                  l = layersArray.length;
                  continue;
                }
                if (!m.getLatLng) {
                  npg.addLayer(m);
                  if (!skipLayerAddEvent) {
                    this.fire("layeradd", { layer: m });
                  }
                  continue;
                }
                if (this.hasLayer(m)) {
                  continue;
                }
                this._addLayer(m, this._maxZoom);
                if (!skipLayerAddEvent) {
                  this.fire("layeradd", { layer: m });
                }
                if (m.__parent) {
                  if (m.__parent.getChildCount() === 2) {
                    var markers = m.__parent.getAllChildMarkers(), otherMarker = markers[0] === m ? markers[1] : markers[0];
                    fg.removeLayer(otherMarker);
                  }
                }
              }
              if (chunkProgress) {
                chunkProgress(offset, l, (/* @__PURE__ */ new Date()).getTime() - started);
              }
              if (offset === l) {
                this._topClusterLevel._recalculateBounds();
                this._refreshClustersIcons();
                this._topClusterLevel._recursivelyAddChildrenToMap(null, this._zoom, this._currentShownBounds);
              } else {
                setTimeout(process, this.options.chunkDelay);
              }
            }, this);
            process();
          } else {
            var needsClustering = this._needsClustering;
            for (; offset < l; offset++) {
              m = layersArray[offset];
              if (m instanceof L.LayerGroup) {
                if (originalArray) {
                  layersArray = layersArray.slice();
                  originalArray = false;
                }
                this._extractNonGroupLayers(m, layersArray);
                l = layersArray.length;
                continue;
              }
              if (!m.getLatLng) {
                npg.addLayer(m);
                continue;
              }
              if (this.hasLayer(m)) {
                continue;
              }
              needsClustering.push(m);
            }
          }
          return this;
        },
        //Takes an array of markers and removes them in bulk
        removeLayers: function(layersArray) {
          var i, m, l = layersArray.length, fg = this._featureGroup, npg = this._nonPointGroup, originalArray = true;
          if (!this._map) {
            for (i = 0; i < l; i++) {
              m = layersArray[i];
              if (m instanceof L.LayerGroup) {
                if (originalArray) {
                  layersArray = layersArray.slice();
                  originalArray = false;
                }
                this._extractNonGroupLayers(m, layersArray);
                l = layersArray.length;
                continue;
              }
              this._arraySplice(this._needsClustering, m);
              npg.removeLayer(m);
              if (this.hasLayer(m)) {
                this._needsRemoving.push({ layer: m, latlng: m._latlng });
              }
              this.fire("layerremove", { layer: m });
            }
            return this;
          }
          if (this._unspiderfy) {
            this._unspiderfy();
            var layersArray2 = layersArray.slice(), l2 = l;
            for (i = 0; i < l2; i++) {
              m = layersArray2[i];
              if (m instanceof L.LayerGroup) {
                this._extractNonGroupLayers(m, layersArray2);
                l2 = layersArray2.length;
                continue;
              }
              this._unspiderfyLayer(m);
            }
          }
          for (i = 0; i < l; i++) {
            m = layersArray[i];
            if (m instanceof L.LayerGroup) {
              if (originalArray) {
                layersArray = layersArray.slice();
                originalArray = false;
              }
              this._extractNonGroupLayers(m, layersArray);
              l = layersArray.length;
              continue;
            }
            if (!m.__parent) {
              npg.removeLayer(m);
              this.fire("layerremove", { layer: m });
              continue;
            }
            this._removeLayer(m, true, true);
            this.fire("layerremove", { layer: m });
            if (fg.hasLayer(m)) {
              fg.removeLayer(m);
              if (m.clusterShow) {
                m.clusterShow();
              }
            }
          }
          this._topClusterLevel._recalculateBounds();
          this._refreshClustersIcons();
          this._topClusterLevel._recursivelyAddChildrenToMap(null, this._zoom, this._currentShownBounds);
          return this;
        },
        //Removes all layers from the MarkerClusterGroup
        clearLayers: function() {
          if (!this._map) {
            this._needsClustering = [];
            this._needsRemoving = [];
            delete this._gridClusters;
            delete this._gridUnclustered;
          }
          if (this._noanimationUnspiderfy) {
            this._noanimationUnspiderfy();
          }
          this._featureGroup.clearLayers();
          this._nonPointGroup.clearLayers();
          this.eachLayer(function(marker) {
            marker.off(this._childMarkerEventHandlers, this);
            delete marker.__parent;
          }, this);
          if (this._map) {
            this._generateInitialClusters();
          }
          return this;
        },
        //Override FeatureGroup.getBounds as it doesn't work
        getBounds: function() {
          var bounds = new L.LatLngBounds();
          if (this._topClusterLevel) {
            bounds.extend(this._topClusterLevel._bounds);
          }
          for (var i = this._needsClustering.length - 1; i >= 0; i--) {
            bounds.extend(this._needsClustering[i].getLatLng());
          }
          bounds.extend(this._nonPointGroup.getBounds());
          return bounds;
        },
        //Overrides LayerGroup.eachLayer
        eachLayer: function(method, context) {
          var markers = this._needsClustering.slice(), needsRemoving = this._needsRemoving, thisNeedsRemoving, i, j;
          if (this._topClusterLevel) {
            this._topClusterLevel.getAllChildMarkers(markers);
          }
          for (i = markers.length - 1; i >= 0; i--) {
            thisNeedsRemoving = true;
            for (j = needsRemoving.length - 1; j >= 0; j--) {
              if (needsRemoving[j].layer === markers[i]) {
                thisNeedsRemoving = false;
                break;
              }
            }
            if (thisNeedsRemoving) {
              method.call(context, markers[i]);
            }
          }
          this._nonPointGroup.eachLayer(method, context);
        },
        //Overrides LayerGroup.getLayers
        getLayers: function() {
          var layers = [];
          this.eachLayer(function(l) {
            layers.push(l);
          });
          return layers;
        },
        //Overrides LayerGroup.getLayer, WARNING: Really bad performance
        getLayer: function(id) {
          var result = null;
          id = parseInt(id, 10);
          this.eachLayer(function(l) {
            if (L.stamp(l) === id) {
              result = l;
            }
          });
          return result;
        },
        //Returns true if the given layer is in this MarkerClusterGroup
        hasLayer: function(layer) {
          if (!layer) {
            return false;
          }
          var i, anArray = this._needsClustering;
          for (i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i] === layer) {
              return true;
            }
          }
          anArray = this._needsRemoving;
          for (i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i].layer === layer) {
              return false;
            }
          }
          return !!(layer.__parent && layer.__parent._group === this) || this._nonPointGroup.hasLayer(layer);
        },
        //Zoom down to show the given layer (spiderfying if necessary) then calls the callback
        zoomToShowLayer: function(layer, callback) {
          var map = this._map;
          if (typeof callback !== "function") {
            callback = function() {
            };
          }
          var showMarker = function() {
            if ((map.hasLayer(layer) || map.hasLayer(layer.__parent)) && !this._inZoomAnimation) {
              this._map.off("moveend", showMarker, this);
              this.off("animationend", showMarker, this);
              if (map.hasLayer(layer)) {
                callback();
              } else if (layer.__parent._icon) {
                this.once("spiderfied", callback, this);
                layer.__parent.spiderfy();
              }
            }
          };
          if (layer._icon && this._map.getBounds().contains(layer.getLatLng())) {
            callback();
          } else if (layer.__parent._zoom < Math.round(this._map._zoom)) {
            this._map.on("moveend", showMarker, this);
            this._map.panTo(layer.getLatLng());
          } else {
            this._map.on("moveend", showMarker, this);
            this.on("animationend", showMarker, this);
            layer.__parent.zoomToBounds();
          }
        },
        //Overrides FeatureGroup.onAdd
        onAdd: function(map) {
          this._map = map;
          var i, l, layer;
          if (!isFinite(this._map.getMaxZoom())) {
            throw "Map has no maxZoom specified";
          }
          this._featureGroup.addTo(map);
          this._nonPointGroup.addTo(map);
          if (!this._gridClusters) {
            this._generateInitialClusters();
          }
          this._maxLat = map.options.crs.projection.MAX_LATITUDE;
          for (i = 0, l = this._needsRemoving.length; i < l; i++) {
            layer = this._needsRemoving[i];
            layer.newlatlng = layer.layer._latlng;
            layer.layer._latlng = layer.latlng;
          }
          for (i = 0, l = this._needsRemoving.length; i < l; i++) {
            layer = this._needsRemoving[i];
            this._removeLayer(layer.layer, true);
            layer.layer._latlng = layer.newlatlng;
          }
          this._needsRemoving = [];
          this._zoom = Math.round(this._map._zoom);
          this._currentShownBounds = this._getExpandedVisibleBounds();
          this._map.on("zoomend", this._zoomEnd, this);
          this._map.on("moveend", this._moveEnd, this);
          if (this._spiderfierOnAdd) {
            this._spiderfierOnAdd();
          }
          this._bindEvents();
          l = this._needsClustering;
          this._needsClustering = [];
          this.addLayers(l, true);
        },
        //Overrides FeatureGroup.onRemove
        onRemove: function(map) {
          map.off("zoomend", this._zoomEnd, this);
          map.off("moveend", this._moveEnd, this);
          this._unbindEvents();
          this._map._mapPane.className = this._map._mapPane.className.replace(" leaflet-cluster-anim", "");
          if (this._spiderfierOnRemove) {
            this._spiderfierOnRemove();
          }
          delete this._maxLat;
          this._hideCoverage();
          this._featureGroup.remove();
          this._nonPointGroup.remove();
          this._featureGroup.clearLayers();
          this._map = null;
        },
        getVisibleParent: function(marker) {
          var vMarker = marker;
          while (vMarker && !vMarker._icon) {
            vMarker = vMarker.__parent;
          }
          return vMarker || null;
        },
        //Remove the given object from the given array
        _arraySplice: function(anArray, obj) {
          for (var i = anArray.length - 1; i >= 0; i--) {
            if (anArray[i] === obj) {
              anArray.splice(i, 1);
              return true;
            }
          }
        },
        /**
         * Removes a marker from all _gridUnclustered zoom levels, starting at the supplied zoom.
         * @param marker to be removed from _gridUnclustered.
         * @param z integer bottom start zoom level (included)
         * @private
         */
        _removeFromGridUnclustered: function(marker, z) {
          var map = this._map, gridUnclustered = this._gridUnclustered, minZoom = Math.floor(this._map.getMinZoom());
          for (; z >= minZoom; z--) {
            if (!gridUnclustered[z].removeObject(marker, map.project(marker.getLatLng(), z))) {
              break;
            }
          }
        },
        _childMarkerDragStart: function(e) {
          e.target.__dragStart = e.target._latlng;
        },
        _childMarkerMoved: function(e) {
          if (!this._ignoreMove && !e.target.__dragStart) {
            var isPopupOpen = e.target._popup && e.target._popup.isOpen();
            this._moveChild(e.target, e.oldLatLng, e.latlng);
            if (isPopupOpen) {
              e.target.openPopup();
            }
          }
        },
        _moveChild: function(layer, from, to) {
          layer._latlng = from;
          this.removeLayer(layer);
          layer._latlng = to;
          this.addLayer(layer);
        },
        _childMarkerDragEnd: function(e) {
          var dragStart = e.target.__dragStart;
          delete e.target.__dragStart;
          if (dragStart) {
            this._moveChild(e.target, dragStart, e.target._latlng);
          }
        },
        //Internal function for removing a marker from everything.
        //dontUpdateMap: set to true if you will handle updating the map manually (for bulk functions)
        _removeLayer: function(marker, removeFromDistanceGrid, dontUpdateMap) {
          var gridClusters = this._gridClusters, gridUnclustered = this._gridUnclustered, fg = this._featureGroup, map = this._map, minZoom = Math.floor(this._map.getMinZoom());
          if (removeFromDistanceGrid) {
            this._removeFromGridUnclustered(marker, this._maxZoom);
          }
          var cluster = marker.__parent, markers = cluster._markers, otherMarker;
          this._arraySplice(markers, marker);
          while (cluster) {
            cluster._childCount--;
            cluster._boundsNeedUpdate = true;
            if (cluster._zoom < minZoom) {
              break;
            } else if (removeFromDistanceGrid && cluster._childCount <= 1) {
              otherMarker = cluster._markers[0] === marker ? cluster._markers[1] : cluster._markers[0];
              gridClusters[cluster._zoom].removeObject(cluster, map.project(cluster._cLatLng, cluster._zoom));
              gridUnclustered[cluster._zoom].addObject(otherMarker, map.project(otherMarker.getLatLng(), cluster._zoom));
              this._arraySplice(cluster.__parent._childClusters, cluster);
              cluster.__parent._markers.push(otherMarker);
              otherMarker.__parent = cluster.__parent;
              if (cluster._icon) {
                fg.removeLayer(cluster);
                if (!dontUpdateMap) {
                  fg.addLayer(otherMarker);
                }
              }
            } else {
              cluster._iconNeedsUpdate = true;
            }
            cluster = cluster.__parent;
          }
          delete marker.__parent;
        },
        _isOrIsParent: function(el, oel) {
          while (oel) {
            if (el === oel) {
              return true;
            }
            oel = oel.parentNode;
          }
          return false;
        },
        //Override L.Evented.fire
        fire: function(type, data, propagate) {
          if (data && data.layer instanceof L.MarkerCluster) {
            if (data.originalEvent && this._isOrIsParent(data.layer._icon, data.originalEvent.relatedTarget)) {
              return;
            }
            type = "cluster" + type;
          }
          L.FeatureGroup.prototype.fire.call(this, type, data, propagate);
        },
        //Override L.Evented.listens
        listens: function(type, propagate) {
          return L.FeatureGroup.prototype.listens.call(this, type, propagate) || L.FeatureGroup.prototype.listens.call(this, "cluster" + type, propagate);
        },
        //Default functionality
        _defaultIconCreateFunction: function(cluster) {
          var childCount = cluster.getChildCount();
          var c = " marker-cluster-";
          if (childCount < 10) {
            c += "small";
          } else if (childCount < 100) {
            c += "medium";
          } else {
            c += "large";
          }
          return new L.DivIcon({ html: "<div><span>" + childCount + "</span></div>", className: "marker-cluster" + c, iconSize: new L.Point(40, 40) });
        },
        _bindEvents: function() {
          var map = this._map, spiderfyOnMaxZoom = this.options.spiderfyOnMaxZoom, showCoverageOnHover = this.options.showCoverageOnHover, zoomToBoundsOnClick = this.options.zoomToBoundsOnClick, spiderfyOnEveryZoom = this.options.spiderfyOnEveryZoom;
          if (spiderfyOnMaxZoom || zoomToBoundsOnClick || spiderfyOnEveryZoom) {
            this.on("clusterclick clusterkeypress", this._zoomOrSpiderfy, this);
          }
          if (showCoverageOnHover) {
            this.on("clustermouseover", this._showCoverage, this);
            this.on("clustermouseout", this._hideCoverage, this);
            map.on("zoomend", this._hideCoverage, this);
          }
        },
        _zoomOrSpiderfy: function(e) {
          var cluster = e.layer, bottomCluster = cluster;
          if (e.type === "clusterkeypress" && e.originalEvent && e.originalEvent.keyCode !== 13) {
            return;
          }
          while (bottomCluster._childClusters.length === 1) {
            bottomCluster = bottomCluster._childClusters[0];
          }
          if (bottomCluster._zoom === this._maxZoom && bottomCluster._childCount === cluster._childCount && this.options.spiderfyOnMaxZoom) {
            cluster.spiderfy();
          } else if (this.options.zoomToBoundsOnClick) {
            cluster.zoomToBounds();
          }
          if (this.options.spiderfyOnEveryZoom) {
            cluster.spiderfy();
          }
          if (e.originalEvent && e.originalEvent.keyCode === 13) {
            this._map._container.focus();
          }
        },
        _showCoverage: function(e) {
          var map = this._map;
          if (this._inZoomAnimation) {
            return;
          }
          if (this._shownPolygon) {
            map.removeLayer(this._shownPolygon);
          }
          if (e.layer.getChildCount() > 2 && e.layer !== this._spiderfied) {
            this._shownPolygon = new L.Polygon(e.layer.getConvexHull(), this.options.polygonOptions);
            map.addLayer(this._shownPolygon);
          }
        },
        _hideCoverage: function() {
          if (this._shownPolygon) {
            this._map.removeLayer(this._shownPolygon);
            this._shownPolygon = null;
          }
        },
        _unbindEvents: function() {
          var spiderfyOnMaxZoom = this.options.spiderfyOnMaxZoom, showCoverageOnHover = this.options.showCoverageOnHover, zoomToBoundsOnClick = this.options.zoomToBoundsOnClick, spiderfyOnEveryZoom = this.options.spiderfyOnEveryZoom, map = this._map;
          if (spiderfyOnMaxZoom || zoomToBoundsOnClick || spiderfyOnEveryZoom) {
            this.off("clusterclick clusterkeypress", this._zoomOrSpiderfy, this);
          }
          if (showCoverageOnHover) {
            this.off("clustermouseover", this._showCoverage, this);
            this.off("clustermouseout", this._hideCoverage, this);
            map.off("zoomend", this._hideCoverage, this);
          }
        },
        _zoomEnd: function() {
          if (!this._map) {
            return;
          }
          this._mergeSplitClusters();
          this._zoom = Math.round(this._map._zoom);
          this._currentShownBounds = this._getExpandedVisibleBounds();
        },
        _moveEnd: function() {
          if (this._inZoomAnimation) {
            return;
          }
          var newBounds = this._getExpandedVisibleBounds();
          this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), this._zoom, newBounds);
          this._topClusterLevel._recursivelyAddChildrenToMap(null, Math.round(this._map._zoom), newBounds);
          this._currentShownBounds = newBounds;
          return;
        },
        _generateInitialClusters: function() {
          var maxZoom = Math.ceil(this._map.getMaxZoom()), minZoom = Math.floor(this._map.getMinZoom()), radius = this.options.maxClusterRadius, radiusFn = radius;
          if (typeof radius !== "function") {
            radiusFn = function() {
              return radius;
            };
          }
          if (this.options.disableClusteringAtZoom !== null) {
            maxZoom = this.options.disableClusteringAtZoom - 1;
          }
          this._maxZoom = maxZoom;
          this._gridClusters = {};
          this._gridUnclustered = {};
          for (var zoom = maxZoom; zoom >= minZoom; zoom--) {
            this._gridClusters[zoom] = new L.DistanceGrid(radiusFn(zoom));
            this._gridUnclustered[zoom] = new L.DistanceGrid(radiusFn(zoom));
          }
          this._topClusterLevel = new this._markerCluster(this, minZoom - 1);
        },
        //Zoom: Zoom to start adding at (Pass this._maxZoom to start at the bottom)
        _addLayer: function(layer, zoom) {
          var gridClusters = this._gridClusters, gridUnclustered = this._gridUnclustered, minZoom = Math.floor(this._map.getMinZoom()), markerPoint, z;
          if (this.options.singleMarkerMode) {
            this._overrideMarkerIcon(layer);
          }
          layer.on(this._childMarkerEventHandlers, this);
          for (; zoom >= minZoom; zoom--) {
            markerPoint = this._map.project(layer.getLatLng(), zoom);
            var closest = gridClusters[zoom].getNearObject(markerPoint);
            if (closest) {
              closest._addChild(layer);
              layer.__parent = closest;
              return;
            }
            closest = gridUnclustered[zoom].getNearObject(markerPoint);
            if (closest) {
              var parent = closest.__parent;
              if (parent) {
                this._removeLayer(closest, false);
              }
              var newCluster = new this._markerCluster(this, zoom, closest, layer);
              gridClusters[zoom].addObject(newCluster, this._map.project(newCluster._cLatLng, zoom));
              closest.__parent = newCluster;
              layer.__parent = newCluster;
              var lastParent = newCluster;
              for (z = zoom - 1; z > parent._zoom; z--) {
                lastParent = new this._markerCluster(this, z, lastParent);
                gridClusters[z].addObject(lastParent, this._map.project(closest.getLatLng(), z));
              }
              parent._addChild(lastParent);
              this._removeFromGridUnclustered(closest, zoom);
              return;
            }
            gridUnclustered[zoom].addObject(layer, markerPoint);
          }
          this._topClusterLevel._addChild(layer);
          layer.__parent = this._topClusterLevel;
          return;
        },
        /**
         * Refreshes the icon of all "dirty" visible clusters.
         * Non-visible "dirty" clusters will be updated when they are added to the map.
         * @private
         */
        _refreshClustersIcons: function() {
          this._featureGroup.eachLayer(function(c) {
            if (c instanceof L.MarkerCluster && c._iconNeedsUpdate) {
              c._updateIcon();
            }
          });
        },
        //Enqueue code to fire after the marker expand/contract has happened
        _enqueue: function(fn) {
          this._queue.push(fn);
          if (!this._queueTimeout) {
            this._queueTimeout = setTimeout(L.bind(this._processQueue, this), 300);
          }
        },
        _processQueue: function() {
          for (var i = 0; i < this._queue.length; i++) {
            this._queue[i].call(this);
          }
          this._queue.length = 0;
          clearTimeout(this._queueTimeout);
          this._queueTimeout = null;
        },
        //Merge and split any existing clusters that are too big or small
        _mergeSplitClusters: function() {
          var mapZoom = Math.round(this._map._zoom);
          this._processQueue();
          if (this._zoom < mapZoom && this._currentShownBounds.intersects(this._getExpandedVisibleBounds())) {
            this._animationStart();
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), this._zoom, this._getExpandedVisibleBounds());
            this._animationZoomIn(this._zoom, mapZoom);
          } else if (this._zoom > mapZoom) {
            this._animationStart();
            this._animationZoomOut(this._zoom, mapZoom);
          } else {
            this._moveEnd();
          }
        },
        //Gets the maps visible bounds expanded in each direction by the size of the screen (so the user cannot see an area we do not cover in one pan)
        _getExpandedVisibleBounds: function() {
          if (!this.options.removeOutsideVisibleBounds) {
            return this._mapBoundsInfinite;
          } else if (L.Browser.mobile) {
            return this._checkBoundsMaxLat(this._map.getBounds());
          }
          return this._checkBoundsMaxLat(this._map.getBounds().pad(1));
        },
        /**
         * Expands the latitude to Infinity (or -Infinity) if the input bounds reach the map projection maximum defined latitude
         * (in the case of Web/Spherical Mercator, it is 85.0511287798 / see https://en.wikipedia.org/wiki/Web_Mercator#Formulas).
         * Otherwise, the removeOutsideVisibleBounds option will remove markers beyond that limit, whereas the same markers without
         * this option (or outside MCG) will have their position floored (ceiled) by the projection and rendered at that limit,
         * making the user think that MCG "eats" them and never displays them again.
         * @param bounds L.LatLngBounds
         * @returns {L.LatLngBounds}
         * @private
         */
        _checkBoundsMaxLat: function(bounds) {
          var maxLat = this._maxLat;
          if (maxLat !== void 0) {
            if (bounds.getNorth() >= maxLat) {
              bounds._northEast.lat = Infinity;
            }
            if (bounds.getSouth() <= -maxLat) {
              bounds._southWest.lat = -Infinity;
            }
          }
          return bounds;
        },
        //Shared animation code
        _animationAddLayerNonAnimated: function(layer, newCluster) {
          if (newCluster === layer) {
            this._featureGroup.addLayer(layer);
          } else if (newCluster._childCount === 2) {
            newCluster._addToMap();
            var markers = newCluster.getAllChildMarkers();
            this._featureGroup.removeLayer(markers[0]);
            this._featureGroup.removeLayer(markers[1]);
          } else {
            newCluster._updateIcon();
          }
        },
        /**
         * Extracts individual (i.e. non-group) layers from a Layer Group.
         * @param group to extract layers from.
         * @param output {Array} in which to store the extracted layers.
         * @returns {*|Array}
         * @private
         */
        _extractNonGroupLayers: function(group, output) {
          var layers = group.getLayers(), i = 0, layer;
          output = output || [];
          for (; i < layers.length; i++) {
            layer = layers[i];
            if (layer instanceof L.LayerGroup) {
              this._extractNonGroupLayers(layer, output);
              continue;
            }
            output.push(layer);
          }
          return output;
        },
        /**
         * Implements the singleMarkerMode option.
         * @param layer Marker to re-style using the Clusters iconCreateFunction.
         * @returns {L.Icon} The newly created icon.
         * @private
         */
        _overrideMarkerIcon: function(layer) {
          var icon = layer.options.icon = this.options.iconCreateFunction({
            getChildCount: function() {
              return 1;
            },
            getAllChildMarkers: function() {
              return [layer];
            }
          });
          return icon;
        }
      });
      L.MarkerClusterGroup.include({
        _mapBoundsInfinite: new L.LatLngBounds(new L.LatLng(-Infinity, -Infinity), new L.LatLng(Infinity, Infinity))
      });
      L.MarkerClusterGroup.include({
        _noAnimation: {
          //Non Animated versions of everything
          _animationStart: function() {
          },
          _animationZoomIn: function(previousZoomLevel, newZoomLevel) {
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this.fire("animationend");
          },
          _animationZoomOut: function(previousZoomLevel, newZoomLevel) {
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this.fire("animationend");
          },
          _animationAddLayer: function(layer, newCluster) {
            this._animationAddLayerNonAnimated(layer, newCluster);
          }
        },
        _withAnimation: {
          //Animated versions here
          _animationStart: function() {
            this._map._mapPane.className += " leaflet-cluster-anim";
            this._inZoomAnimation++;
          },
          _animationZoomIn: function(previousZoomLevel, newZoomLevel) {
            var bounds = this._getExpandedVisibleBounds(), fg = this._featureGroup, minZoom = Math.floor(this._map.getMinZoom()), i;
            this._ignoreMove = true;
            this._topClusterLevel._recursively(bounds, previousZoomLevel, minZoom, function(c) {
              var startPos = c._latlng, markers = c._markers, m;
              if (!bounds.contains(startPos)) {
                startPos = null;
              }
              if (c._isSingleParent() && previousZoomLevel + 1 === newZoomLevel) {
                fg.removeLayer(c);
                c._recursivelyAddChildrenToMap(null, newZoomLevel, bounds);
              } else {
                c.clusterHide();
                c._recursivelyAddChildrenToMap(startPos, newZoomLevel, bounds);
              }
              for (i = markers.length - 1; i >= 0; i--) {
                m = markers[i];
                if (!bounds.contains(m._latlng)) {
                  fg.removeLayer(m);
                }
              }
            });
            this._forceLayout();
            this._topClusterLevel._recursivelyBecomeVisible(bounds, newZoomLevel);
            fg.eachLayer(function(n) {
              if (!(n instanceof L.MarkerCluster) && n._icon) {
                n.clusterShow();
              }
            });
            this._topClusterLevel._recursively(bounds, previousZoomLevel, newZoomLevel, function(c) {
              c._recursivelyRestoreChildPositions(newZoomLevel);
            });
            this._ignoreMove = false;
            this._enqueue(function() {
              this._topClusterLevel._recursively(bounds, previousZoomLevel, minZoom, function(c) {
                fg.removeLayer(c);
                c.clusterShow();
              });
              this._animationEnd();
            });
          },
          _animationZoomOut: function(previousZoomLevel, newZoomLevel) {
            this._animationZoomOutSingle(this._topClusterLevel, previousZoomLevel - 1, newZoomLevel);
            this._topClusterLevel._recursivelyAddChildrenToMap(null, newZoomLevel, this._getExpandedVisibleBounds());
            this._topClusterLevel._recursivelyRemoveChildrenFromMap(this._currentShownBounds, Math.floor(this._map.getMinZoom()), previousZoomLevel, this._getExpandedVisibleBounds());
          },
          _animationAddLayer: function(layer, newCluster) {
            var me = this, fg = this._featureGroup;
            fg.addLayer(layer);
            if (newCluster !== layer) {
              if (newCluster._childCount > 2) {
                newCluster._updateIcon();
                this._forceLayout();
                this._animationStart();
                layer._setPos(this._map.latLngToLayerPoint(newCluster.getLatLng()));
                layer.clusterHide();
                this._enqueue(function() {
                  fg.removeLayer(layer);
                  layer.clusterShow();
                  me._animationEnd();
                });
              } else {
                this._forceLayout();
                me._animationStart();
                me._animationZoomOutSingle(newCluster, this._map.getMaxZoom(), this._zoom);
              }
            }
          }
        },
        // Private methods for animated versions.
        _animationZoomOutSingle: function(cluster, previousZoomLevel, newZoomLevel) {
          var bounds = this._getExpandedVisibleBounds(), minZoom = Math.floor(this._map.getMinZoom());
          cluster._recursivelyAnimateChildrenInAndAddSelfToMap(bounds, minZoom, previousZoomLevel + 1, newZoomLevel);
          var me = this;
          this._forceLayout();
          cluster._recursivelyBecomeVisible(bounds, newZoomLevel);
          this._enqueue(function() {
            if (cluster._childCount === 1) {
              var m = cluster._markers[0];
              this._ignoreMove = true;
              m.setLatLng(m.getLatLng());
              this._ignoreMove = false;
              if (m.clusterShow) {
                m.clusterShow();
              }
            } else {
              cluster._recursively(bounds, newZoomLevel, minZoom, function(c) {
                c._recursivelyRemoveChildrenFromMap(bounds, minZoom, previousZoomLevel + 1);
              });
            }
            me._animationEnd();
          });
        },
        _animationEnd: function() {
          if (this._map) {
            this._map._mapPane.className = this._map._mapPane.className.replace(" leaflet-cluster-anim", "");
          }
          this._inZoomAnimation--;
          this.fire("animationend");
        },
        //Force a browser layout of stuff in the map
        // Should apply the current opacity and location to all elements so we can update them again for an animation
        _forceLayout: function() {
          L.Util.falseFn(document.body.offsetWidth);
        }
      });
      L.markerClusterGroup = function(options) {
        return new L.MarkerClusterGroup(options);
      };
      var MarkerCluster = L.MarkerCluster = L.Marker.extend({
        options: L.Icon.prototype.options,
        initialize: function(group, zoom, a, b) {
          L.Marker.prototype.initialize.call(
            this,
            a ? a._cLatLng || a.getLatLng() : new L.LatLng(0, 0),
            { icon: this, pane: group.options.clusterPane }
          );
          this._group = group;
          this._zoom = zoom;
          this._markers = [];
          this._childClusters = [];
          this._childCount = 0;
          this._iconNeedsUpdate = true;
          this._boundsNeedUpdate = true;
          this._bounds = new L.LatLngBounds();
          if (a) {
            this._addChild(a);
          }
          if (b) {
            this._addChild(b);
          }
        },
        //Recursively retrieve all child markers of this cluster
        getAllChildMarkers: function(storageArray, ignoreDraggedMarker) {
          storageArray = storageArray || [];
          for (var i = this._childClusters.length - 1; i >= 0; i--) {
            this._childClusters[i].getAllChildMarkers(storageArray, ignoreDraggedMarker);
          }
          for (var j = this._markers.length - 1; j >= 0; j--) {
            if (ignoreDraggedMarker && this._markers[j].__dragStart) {
              continue;
            }
            storageArray.push(this._markers[j]);
          }
          return storageArray;
        },
        //Returns the count of how many child markers we have
        getChildCount: function() {
          return this._childCount;
        },
        //Zoom to the minimum of showing all of the child markers, or the extents of this cluster
        zoomToBounds: function(fitBoundsOptions) {
          var childClusters = this._childClusters.slice(), map = this._group._map, boundsZoom = map.getBoundsZoom(this._bounds), zoom = this._zoom + 1, mapZoom = map.getZoom(), i;
          while (childClusters.length > 0 && boundsZoom > zoom) {
            zoom++;
            var newClusters = [];
            for (i = 0; i < childClusters.length; i++) {
              newClusters = newClusters.concat(childClusters[i]._childClusters);
            }
            childClusters = newClusters;
          }
          if (boundsZoom > zoom) {
            this._group._map.setView(this._latlng, zoom);
          } else if (boundsZoom <= mapZoom) {
            this._group._map.setView(this._latlng, mapZoom + 1);
          } else {
            this._group._map.fitBounds(this._bounds, fitBoundsOptions);
          }
        },
        getBounds: function() {
          var bounds = new L.LatLngBounds();
          bounds.extend(this._bounds);
          return bounds;
        },
        _updateIcon: function() {
          this._iconNeedsUpdate = true;
          if (this._icon) {
            this.setIcon(this);
          }
        },
        //Cludge for Icon, we pretend to be an icon for performance
        createIcon: function() {
          if (this._iconNeedsUpdate) {
            this._iconObj = this._group.options.iconCreateFunction(this);
            this._iconNeedsUpdate = false;
          }
          return this._iconObj.createIcon();
        },
        createShadow: function() {
          return this._iconObj.createShadow();
        },
        _addChild: function(new1, isNotificationFromChild) {
          this._iconNeedsUpdate = true;
          this._boundsNeedUpdate = true;
          this._setClusterCenter(new1);
          if (new1 instanceof L.MarkerCluster) {
            if (!isNotificationFromChild) {
              this._childClusters.push(new1);
              new1.__parent = this;
            }
            this._childCount += new1._childCount;
          } else {
            if (!isNotificationFromChild) {
              this._markers.push(new1);
            }
            this._childCount++;
          }
          if (this.__parent) {
            this.__parent._addChild(new1, true);
          }
        },
        /**
         * Makes sure the cluster center is set. If not, uses the child center if it is a cluster, or the marker position.
         * @param child L.MarkerCluster|L.Marker that will be used as cluster center if not defined yet.
         * @private
         */
        _setClusterCenter: function(child) {
          if (!this._cLatLng) {
            this._cLatLng = child._cLatLng || child._latlng;
          }
        },
        /**
         * Assigns impossible bounding values so that the next extend entirely determines the new bounds.
         * This method avoids having to trash the previous L.LatLngBounds object and to create a new one, which is much slower for this class.
         * As long as the bounds are not extended, most other methods would probably fail, as they would with bounds initialized but not extended.
         * @private
         */
        _resetBounds: function() {
          var bounds = this._bounds;
          if (bounds._southWest) {
            bounds._southWest.lat = Infinity;
            bounds._southWest.lng = Infinity;
          }
          if (bounds._northEast) {
            bounds._northEast.lat = -Infinity;
            bounds._northEast.lng = -Infinity;
          }
        },
        _recalculateBounds: function() {
          var markers = this._markers, childClusters = this._childClusters, latSum = 0, lngSum = 0, totalCount = this._childCount, i, child, childLatLng, childCount;
          if (totalCount === 0) {
            return;
          }
          this._resetBounds();
          for (i = 0; i < markers.length; i++) {
            childLatLng = markers[i]._latlng;
            this._bounds.extend(childLatLng);
            latSum += childLatLng.lat;
            lngSum += childLatLng.lng;
          }
          for (i = 0; i < childClusters.length; i++) {
            child = childClusters[i];
            if (child._boundsNeedUpdate) {
              child._recalculateBounds();
            }
            this._bounds.extend(child._bounds);
            childLatLng = child._wLatLng;
            childCount = child._childCount;
            latSum += childLatLng.lat * childCount;
            lngSum += childLatLng.lng * childCount;
          }
          this._latlng = this._wLatLng = new L.LatLng(latSum / totalCount, lngSum / totalCount);
          this._boundsNeedUpdate = false;
        },
        //Set our markers position as given and add it to the map
        _addToMap: function(startPos) {
          if (startPos) {
            this._backupLatlng = this._latlng;
            this.setLatLng(startPos);
          }
          this._group._featureGroup.addLayer(this);
        },
        _recursivelyAnimateChildrenIn: function(bounds, center, maxZoom) {
          this._recursively(
            bounds,
            this._group._map.getMinZoom(),
            maxZoom - 1,
            function(c) {
              var markers = c._markers, i, m;
              for (i = markers.length - 1; i >= 0; i--) {
                m = markers[i];
                if (m._icon) {
                  m._setPos(center);
                  m.clusterHide();
                }
              }
            },
            function(c) {
              var childClusters = c._childClusters, j, cm;
              for (j = childClusters.length - 1; j >= 0; j--) {
                cm = childClusters[j];
                if (cm._icon) {
                  cm._setPos(center);
                  cm.clusterHide();
                }
              }
            }
          );
        },
        _recursivelyAnimateChildrenInAndAddSelfToMap: function(bounds, mapMinZoom, previousZoomLevel, newZoomLevel) {
          this._recursively(
            bounds,
            newZoomLevel,
            mapMinZoom,
            function(c) {
              c._recursivelyAnimateChildrenIn(bounds, c._group._map.latLngToLayerPoint(c.getLatLng()).round(), previousZoomLevel);
              if (c._isSingleParent() && previousZoomLevel - 1 === newZoomLevel) {
                c.clusterShow();
                c._recursivelyRemoveChildrenFromMap(bounds, mapMinZoom, previousZoomLevel);
              } else {
                c.clusterHide();
              }
              c._addToMap();
            }
          );
        },
        _recursivelyBecomeVisible: function(bounds, zoomLevel) {
          this._recursively(bounds, this._group._map.getMinZoom(), zoomLevel, null, function(c) {
            c.clusterShow();
          });
        },
        _recursivelyAddChildrenToMap: function(startPos, zoomLevel, bounds) {
          this._recursively(
            bounds,
            this._group._map.getMinZoom() - 1,
            zoomLevel,
            function(c) {
              if (zoomLevel === c._zoom) {
                return;
              }
              for (var i = c._markers.length - 1; i >= 0; i--) {
                var nm = c._markers[i];
                if (!bounds.contains(nm._latlng)) {
                  continue;
                }
                if (startPos) {
                  nm._backupLatlng = nm.getLatLng();
                  nm.setLatLng(startPos);
                  if (nm.clusterHide) {
                    nm.clusterHide();
                  }
                }
                c._group._featureGroup.addLayer(nm);
              }
            },
            function(c) {
              c._addToMap(startPos);
            }
          );
        },
        _recursivelyRestoreChildPositions: function(zoomLevel) {
          for (var i = this._markers.length - 1; i >= 0; i--) {
            var nm = this._markers[i];
            if (nm._backupLatlng) {
              nm.setLatLng(nm._backupLatlng);
              delete nm._backupLatlng;
            }
          }
          if (zoomLevel - 1 === this._zoom) {
            for (var j = this._childClusters.length - 1; j >= 0; j--) {
              this._childClusters[j]._restorePosition();
            }
          } else {
            for (var k = this._childClusters.length - 1; k >= 0; k--) {
              this._childClusters[k]._recursivelyRestoreChildPositions(zoomLevel);
            }
          }
        },
        _restorePosition: function() {
          if (this._backupLatlng) {
            this.setLatLng(this._backupLatlng);
            delete this._backupLatlng;
          }
        },
        //exceptBounds: If set, don't remove any markers/clusters in it
        _recursivelyRemoveChildrenFromMap: function(previousBounds, mapMinZoom, zoomLevel, exceptBounds) {
          var m, i;
          this._recursively(
            previousBounds,
            mapMinZoom - 1,
            zoomLevel - 1,
            function(c) {
              for (i = c._markers.length - 1; i >= 0; i--) {
                m = c._markers[i];
                if (!exceptBounds || !exceptBounds.contains(m._latlng)) {
                  c._group._featureGroup.removeLayer(m);
                  if (m.clusterShow) {
                    m.clusterShow();
                  }
                }
              }
            },
            function(c) {
              for (i = c._childClusters.length - 1; i >= 0; i--) {
                m = c._childClusters[i];
                if (!exceptBounds || !exceptBounds.contains(m._latlng)) {
                  c._group._featureGroup.removeLayer(m);
                  if (m.clusterShow) {
                    m.clusterShow();
                  }
                }
              }
            }
          );
        },
        //Run the given functions recursively to this and child clusters
        // boundsToApplyTo: a L.LatLngBounds representing the bounds of what clusters to recurse in to
        // zoomLevelToStart: zoom level to start running functions (inclusive)
        // zoomLevelToStop: zoom level to stop running functions (inclusive)
        // runAtEveryLevel: function that takes an L.MarkerCluster as an argument that should be applied on every level
        // runAtBottomLevel: function that takes an L.MarkerCluster as an argument that should be applied at only the bottom level
        _recursively: function(boundsToApplyTo, zoomLevelToStart, zoomLevelToStop, runAtEveryLevel, runAtBottomLevel) {
          var childClusters = this._childClusters, zoom = this._zoom, i, c;
          if (zoomLevelToStart <= zoom) {
            if (runAtEveryLevel) {
              runAtEveryLevel(this);
            }
            if (runAtBottomLevel && zoom === zoomLevelToStop) {
              runAtBottomLevel(this);
            }
          }
          if (zoom < zoomLevelToStart || zoom < zoomLevelToStop) {
            for (i = childClusters.length - 1; i >= 0; i--) {
              c = childClusters[i];
              if (c._boundsNeedUpdate) {
                c._recalculateBounds();
              }
              if (boundsToApplyTo.intersects(c._bounds)) {
                c._recursively(boundsToApplyTo, zoomLevelToStart, zoomLevelToStop, runAtEveryLevel, runAtBottomLevel);
              }
            }
          }
        },
        //Returns true if we are the parent of only one cluster and that cluster is the same as us
        _isSingleParent: function() {
          return this._childClusters.length > 0 && this._childClusters[0]._childCount === this._childCount;
        }
      });
      L.Marker.include({
        clusterHide: function() {
          var backup = this.options.opacity;
          this.setOpacity(0);
          this.options.opacity = backup;
          return this;
        },
        clusterShow: function() {
          return this.setOpacity(this.options.opacity);
        }
      });
      L.DistanceGrid = function(cellSize) {
        this._cellSize = cellSize;
        this._sqCellSize = cellSize * cellSize;
        this._grid = {};
        this._objectPoint = {};
      };
      L.DistanceGrid.prototype = {
        addObject: function(obj, point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), grid = this._grid, row = grid[y] = grid[y] || {}, cell = row[x] = row[x] || [], stamp = L.Util.stamp(obj);
          this._objectPoint[stamp] = point;
          cell.push(obj);
        },
        updateObject: function(obj, point) {
          this.removeObject(obj);
          this.addObject(obj, point);
        },
        //Returns true if the object was found
        removeObject: function(obj, point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), grid = this._grid, row = grid[y] = grid[y] || {}, cell = row[x] = row[x] || [], i, len;
          delete this._objectPoint[L.Util.stamp(obj)];
          for (i = 0, len = cell.length; i < len; i++) {
            if (cell[i] === obj) {
              cell.splice(i, 1);
              if (len === 1) {
                delete row[x];
              }
              return true;
            }
          }
        },
        eachObject: function(fn, context) {
          var i, j, k, len, row, cell, removed, grid = this._grid;
          for (i in grid) {
            row = grid[i];
            for (j in row) {
              cell = row[j];
              for (k = 0, len = cell.length; k < len; k++) {
                removed = fn.call(context, cell[k]);
                if (removed) {
                  k--;
                  len--;
                }
              }
            }
          }
        },
        getNearObject: function(point) {
          var x = this._getCoord(point.x), y = this._getCoord(point.y), i, j, k, row, cell, len, obj, dist, objectPoint = this._objectPoint, closestDistSq = this._sqCellSize, closest = null;
          for (i = y - 1; i <= y + 1; i++) {
            row = this._grid[i];
            if (row) {
              for (j = x - 1; j <= x + 1; j++) {
                cell = row[j];
                if (cell) {
                  for (k = 0, len = cell.length; k < len; k++) {
                    obj = cell[k];
                    dist = this._sqDist(objectPoint[L.Util.stamp(obj)], point);
                    if (dist < closestDistSq || dist <= closestDistSq && closest === null) {
                      closestDistSq = dist;
                      closest = obj;
                    }
                  }
                }
              }
            }
          }
          return closest;
        },
        _getCoord: function(x) {
          var coord = Math.floor(x / this._cellSize);
          return isFinite(coord) ? coord : x;
        },
        _sqDist: function(p, p2) {
          var dx = p2.x - p.x, dy = p2.y - p.y;
          return dx * dx + dy * dy;
        }
      };
      (function() {
        L.QuickHull = {
          /*
           * @param {Object} cpt a point to be measured from the baseline
           * @param {Array} bl the baseline, as represented by a two-element
           *   array of latlng objects.
           * @returns {Number} an approximate distance measure
           */
          getDistant: function(cpt, bl) {
            var vY = bl[1].lat - bl[0].lat, vX = bl[0].lng - bl[1].lng;
            return vX * (cpt.lat - bl[0].lat) + vY * (cpt.lng - bl[0].lng);
          },
          /*
           * @param {Array} baseLine a two-element array of latlng objects
           *   representing the baseline to project from
           * @param {Array} latLngs an array of latlng objects
           * @returns {Object} the maximum point and all new points to stay
           *   in consideration for the hull.
           */
          findMostDistantPointFromBaseLine: function(baseLine, latLngs) {
            var maxD = 0, maxPt = null, newPoints = [], i, pt, d;
            for (i = latLngs.length - 1; i >= 0; i--) {
              pt = latLngs[i];
              d = this.getDistant(pt, baseLine);
              if (d > 0) {
                newPoints.push(pt);
              } else {
                continue;
              }
              if (d > maxD) {
                maxD = d;
                maxPt = pt;
              }
            }
            return { maxPoint: maxPt, newPoints };
          },
          /*
           * Given a baseline, compute the convex hull of latLngs as an array
           * of latLngs.
           *
           * @param {Array} latLngs
           * @returns {Array}
           */
          buildConvexHull: function(baseLine, latLngs) {
            var convexHullBaseLines = [], t = this.findMostDistantPointFromBaseLine(baseLine, latLngs);
            if (t.maxPoint) {
              convexHullBaseLines = convexHullBaseLines.concat(
                this.buildConvexHull([baseLine[0], t.maxPoint], t.newPoints)
              );
              convexHullBaseLines = convexHullBaseLines.concat(
                this.buildConvexHull([t.maxPoint, baseLine[1]], t.newPoints)
              );
              return convexHullBaseLines;
            } else {
              return [baseLine[0]];
            }
          },
          /*
           * Given an array of latlngs, compute a convex hull as an array
           * of latlngs
           *
           * @param {Array} latLngs
           * @returns {Array}
           */
          getConvexHull: function(latLngs) {
            var maxLat = false, minLat = false, maxLng = false, minLng = false, maxLatPt = null, minLatPt = null, maxLngPt = null, minLngPt = null, maxPt = null, minPt = null, i;
            for (i = latLngs.length - 1; i >= 0; i--) {
              var pt = latLngs[i];
              if (maxLat === false || pt.lat > maxLat) {
                maxLatPt = pt;
                maxLat = pt.lat;
              }
              if (minLat === false || pt.lat < minLat) {
                minLatPt = pt;
                minLat = pt.lat;
              }
              if (maxLng === false || pt.lng > maxLng) {
                maxLngPt = pt;
                maxLng = pt.lng;
              }
              if (minLng === false || pt.lng < minLng) {
                minLngPt = pt;
                minLng = pt.lng;
              }
            }
            if (minLat !== maxLat) {
              minPt = minLatPt;
              maxPt = maxLatPt;
            } else {
              minPt = minLngPt;
              maxPt = maxLngPt;
            }
            var ch = [].concat(
              this.buildConvexHull([minPt, maxPt], latLngs),
              this.buildConvexHull([maxPt, minPt], latLngs)
            );
            return ch;
          }
        };
      })();
      L.MarkerCluster.include({
        getConvexHull: function() {
          var childMarkers = this.getAllChildMarkers(), points = [], p, i;
          for (i = childMarkers.length - 1; i >= 0; i--) {
            p = childMarkers[i].getLatLng();
            points.push(p);
          }
          return L.QuickHull.getConvexHull(points);
        }
      });
      L.MarkerCluster.include({
        _2PI: Math.PI * 2,
        _circleFootSeparation: 25,
        //related to circumference of circle
        _circleStartAngle: 0,
        _spiralFootSeparation: 28,
        //related to size of spiral (experiment!)
        _spiralLengthStart: 11,
        _spiralLengthFactor: 5,
        _circleSpiralSwitchover: 9,
        //show spiral instead of circle from this marker count upwards.
        // 0 -> always spiral; Infinity -> always circle
        spiderfy: function() {
          if (this._group._spiderfied === this || this._group._inZoomAnimation) {
            return;
          }
          var childMarkers = this.getAllChildMarkers(null, true), group = this._group, map = group._map, center = map.latLngToLayerPoint(this._latlng), positions;
          this._group._unspiderfy();
          this._group._spiderfied = this;
          if (this._group.options.spiderfyShapePositions) {
            positions = this._group.options.spiderfyShapePositions(childMarkers.length, center);
          } else if (childMarkers.length >= this._circleSpiralSwitchover) {
            positions = this._generatePointsSpiral(childMarkers.length, center);
          } else {
            center.y += 10;
            positions = this._generatePointsCircle(childMarkers.length, center);
          }
          this._animationSpiderfy(childMarkers, positions);
        },
        unspiderfy: function(zoomDetails) {
          if (this._group._inZoomAnimation) {
            return;
          }
          this._animationUnspiderfy(zoomDetails);
          this._group._spiderfied = null;
        },
        _generatePointsCircle: function(count, centerPt) {
          var circumference = this._group.options.spiderfyDistanceMultiplier * this._circleFootSeparation * (2 + count), legLength = circumference / this._2PI, angleStep = this._2PI / count, res = [], i, angle;
          legLength = Math.max(legLength, 35);
          res.length = count;
          for (i = 0; i < count; i++) {
            angle = this._circleStartAngle + i * angleStep;
            res[i] = new L.Point(centerPt.x + legLength * Math.cos(angle), centerPt.y + legLength * Math.sin(angle))._round();
          }
          return res;
        },
        _generatePointsSpiral: function(count, centerPt) {
          var spiderfyDistanceMultiplier = this._group.options.spiderfyDistanceMultiplier, legLength = spiderfyDistanceMultiplier * this._spiralLengthStart, separation = spiderfyDistanceMultiplier * this._spiralFootSeparation, lengthFactor = spiderfyDistanceMultiplier * this._spiralLengthFactor * this._2PI, angle = 0, res = [], i;
          res.length = count;
          for (i = count; i >= 0; i--) {
            if (i < count) {
              res[i] = new L.Point(centerPt.x + legLength * Math.cos(angle), centerPt.y + legLength * Math.sin(angle))._round();
            }
            angle += separation / legLength + i * 5e-4;
            legLength += lengthFactor / angle;
          }
          return res;
        },
        _noanimationUnspiderfy: function() {
          var group = this._group, map = group._map, fg = group._featureGroup, childMarkers = this.getAllChildMarkers(null, true), m, i;
          group._ignoreMove = true;
          this.setOpacity(1);
          for (i = childMarkers.length - 1; i >= 0; i--) {
            m = childMarkers[i];
            fg.removeLayer(m);
            if (m._preSpiderfyLatlng) {
              m.setLatLng(m._preSpiderfyLatlng);
              delete m._preSpiderfyLatlng;
            }
            if (m.setZIndexOffset) {
              m.setZIndexOffset(0);
            }
            if (m._spiderLeg) {
              map.removeLayer(m._spiderLeg);
              delete m._spiderLeg;
            }
          }
          group.fire("unspiderfied", {
            cluster: this,
            markers: childMarkers
          });
          group._ignoreMove = false;
          group._spiderfied = null;
        }
      });
      L.MarkerClusterNonAnimated = L.MarkerCluster.extend({
        _animationSpiderfy: function(childMarkers, positions) {
          var group = this._group, map = group._map, fg = group._featureGroup, legOptions = this._group.options.spiderLegPolylineOptions, i, m, leg, newPos;
          group._ignoreMove = true;
          for (i = 0; i < childMarkers.length; i++) {
            newPos = map.layerPointToLatLng(positions[i]);
            m = childMarkers[i];
            leg = new L.Polyline([this._latlng, newPos], legOptions);
            map.addLayer(leg);
            m._spiderLeg = leg;
            m._preSpiderfyLatlng = m._latlng;
            m.setLatLng(newPos);
            if (m.setZIndexOffset) {
              m.setZIndexOffset(1e6);
            }
            fg.addLayer(m);
          }
          this.setOpacity(0.3);
          group._ignoreMove = false;
          group.fire("spiderfied", {
            cluster: this,
            markers: childMarkers
          });
        },
        _animationUnspiderfy: function() {
          this._noanimationUnspiderfy();
        }
      });
      L.MarkerCluster.include({
        _animationSpiderfy: function(childMarkers, positions) {
          var me = this, group = this._group, map = group._map, fg = group._featureGroup, thisLayerLatLng = this._latlng, thisLayerPos = map.latLngToLayerPoint(thisLayerLatLng), svg = L.Path.SVG, legOptions = L.extend({}, this._group.options.spiderLegPolylineOptions), finalLegOpacity = legOptions.opacity, i, m, leg, legPath, legLength, newPos;
          if (finalLegOpacity === void 0) {
            finalLegOpacity = L.MarkerClusterGroup.prototype.options.spiderLegPolylineOptions.opacity;
          }
          if (svg) {
            legOptions.opacity = 0;
            legOptions.className = (legOptions.className || "") + " leaflet-cluster-spider-leg";
          } else {
            legOptions.opacity = finalLegOpacity;
          }
          group._ignoreMove = true;
          for (i = 0; i < childMarkers.length; i++) {
            m = childMarkers[i];
            newPos = map.layerPointToLatLng(positions[i]);
            leg = new L.Polyline([thisLayerLatLng, newPos], legOptions);
            map.addLayer(leg);
            m._spiderLeg = leg;
            if (svg) {
              legPath = leg._path;
              legLength = legPath.getTotalLength() + 0.1;
              legPath.style.strokeDasharray = legLength;
              legPath.style.strokeDashoffset = legLength;
            }
            if (m.setZIndexOffset) {
              m.setZIndexOffset(1e6);
            }
            if (m.clusterHide) {
              m.clusterHide();
            }
            fg.addLayer(m);
            if (m._setPos) {
              m._setPos(thisLayerPos);
            }
          }
          group._forceLayout();
          group._animationStart();
          for (i = childMarkers.length - 1; i >= 0; i--) {
            newPos = map.layerPointToLatLng(positions[i]);
            m = childMarkers[i];
            m._preSpiderfyLatlng = m._latlng;
            m.setLatLng(newPos);
            if (m.clusterShow) {
              m.clusterShow();
            }
            if (svg) {
              leg = m._spiderLeg;
              legPath = leg._path;
              legPath.style.strokeDashoffset = 0;
              leg.setStyle({ opacity: finalLegOpacity });
            }
          }
          this.setOpacity(0.3);
          group._ignoreMove = false;
          setTimeout(function() {
            group._animationEnd();
            group.fire("spiderfied", {
              cluster: me,
              markers: childMarkers
            });
          }, 200);
        },
        _animationUnspiderfy: function(zoomDetails) {
          var me = this, group = this._group, map = group._map, fg = group._featureGroup, thisLayerPos = zoomDetails ? map._latLngToNewLayerPoint(this._latlng, zoomDetails.zoom, zoomDetails.center) : map.latLngToLayerPoint(this._latlng), childMarkers = this.getAllChildMarkers(null, true), svg = L.Path.SVG, m, i, leg, legPath, legLength, nonAnimatable;
          group._ignoreMove = true;
          group._animationStart();
          this.setOpacity(1);
          for (i = childMarkers.length - 1; i >= 0; i--) {
            m = childMarkers[i];
            if (!m._preSpiderfyLatlng) {
              continue;
            }
            m.closePopup();
            m.setLatLng(m._preSpiderfyLatlng);
            delete m._preSpiderfyLatlng;
            nonAnimatable = true;
            if (m._setPos) {
              m._setPos(thisLayerPos);
              nonAnimatable = false;
            }
            if (m.clusterHide) {
              m.clusterHide();
              nonAnimatable = false;
            }
            if (nonAnimatable) {
              fg.removeLayer(m);
            }
            if (svg) {
              leg = m._spiderLeg;
              legPath = leg._path;
              legLength = legPath.getTotalLength() + 0.1;
              legPath.style.strokeDashoffset = legLength;
              leg.setStyle({ opacity: 0 });
            }
          }
          group._ignoreMove = false;
          setTimeout(function() {
            var stillThereChildCount = 0;
            for (i = childMarkers.length - 1; i >= 0; i--) {
              m = childMarkers[i];
              if (m._spiderLeg) {
                stillThereChildCount++;
              }
            }
            for (i = childMarkers.length - 1; i >= 0; i--) {
              m = childMarkers[i];
              if (!m._spiderLeg) {
                continue;
              }
              if (m.clusterShow) {
                m.clusterShow();
              }
              if (m.setZIndexOffset) {
                m.setZIndexOffset(0);
              }
              if (stillThereChildCount > 1) {
                fg.removeLayer(m);
              }
              map.removeLayer(m._spiderLeg);
              delete m._spiderLeg;
            }
            group._animationEnd();
            group.fire("unspiderfied", {
              cluster: me,
              markers: childMarkers
            });
          }, 200);
        }
      });
      L.MarkerClusterGroup.include({
        //The MarkerCluster currently spiderfied (if any)
        _spiderfied: null,
        unspiderfy: function() {
          this._unspiderfy.apply(this, arguments);
        },
        _spiderfierOnAdd: function() {
          this._map.on("click", this._unspiderfyWrapper, this);
          if (this._map.options.zoomAnimation) {
            this._map.on("zoomstart", this._unspiderfyZoomStart, this);
          }
          this._map.on("zoomend", this._noanimationUnspiderfy, this);
          if (!L.Browser.touch) {
            this._map.getRenderer(this);
          }
        },
        _spiderfierOnRemove: function() {
          this._map.off("click", this._unspiderfyWrapper, this);
          this._map.off("zoomstart", this._unspiderfyZoomStart, this);
          this._map.off("zoomanim", this._unspiderfyZoomAnim, this);
          this._map.off("zoomend", this._noanimationUnspiderfy, this);
          this._noanimationUnspiderfy();
        },
        //On zoom start we add a zoomanim handler so that we are guaranteed to be last (after markers are animated)
        //This means we can define the animation they do rather than Markers doing an animation to their actual location
        _unspiderfyZoomStart: function() {
          if (!this._map) {
            return;
          }
          this._map.on("zoomanim", this._unspiderfyZoomAnim, this);
        },
        _unspiderfyZoomAnim: function(zoomDetails) {
          if (L.DomUtil.hasClass(this._map._mapPane, "leaflet-touching")) {
            return;
          }
          this._map.off("zoomanim", this._unspiderfyZoomAnim, this);
          this._unspiderfy(zoomDetails);
        },
        _unspiderfyWrapper: function() {
          this._unspiderfy();
        },
        _unspiderfy: function(zoomDetails) {
          if (this._spiderfied) {
            this._spiderfied.unspiderfy(zoomDetails);
          }
        },
        _noanimationUnspiderfy: function() {
          if (this._spiderfied) {
            this._spiderfied._noanimationUnspiderfy();
          }
        },
        //If the given layer is currently being spiderfied then we unspiderfy it so it isn't on the map anymore etc
        _unspiderfyLayer: function(layer) {
          if (layer._spiderLeg) {
            this._featureGroup.removeLayer(layer);
            if (layer.clusterShow) {
              layer.clusterShow();
            }
            if (layer.setZIndexOffset) {
              layer.setZIndexOffset(0);
            }
            this._map.removeLayer(layer._spiderLeg);
            delete layer._spiderLeg;
          }
        }
      });
      L.MarkerClusterGroup.include({
        /**
         * Updates the icon of all clusters which are parents of the given marker(s).
         * In singleMarkerMode, also updates the given marker(s) icon.
         * @param layers L.MarkerClusterGroup|L.LayerGroup|Array(L.Marker)|Map(L.Marker)|
         * L.MarkerCluster|L.Marker (optional) list of markers (or single marker) whose parent
         * clusters need to be updated. If not provided, retrieves all child markers of this.
         * @returns {L.MarkerClusterGroup}
         */
        refreshClusters: function(layers) {
          if (!layers) {
            layers = this._topClusterLevel.getAllChildMarkers();
          } else if (layers instanceof L.MarkerClusterGroup) {
            layers = layers._topClusterLevel.getAllChildMarkers();
          } else if (layers instanceof L.LayerGroup) {
            layers = layers._layers;
          } else if (layers instanceof L.MarkerCluster) {
            layers = layers.getAllChildMarkers();
          } else if (layers instanceof L.Marker) {
            layers = [layers];
          }
          this._flagParentsIconsNeedUpdate(layers);
          this._refreshClustersIcons();
          if (this.options.singleMarkerMode) {
            this._refreshSingleMarkerModeMarkers(layers);
          }
          return this;
        },
        /**
         * Simply flags all parent clusters of the given markers as having a "dirty" icon.
         * @param layers Array(L.Marker)|Map(L.Marker) list of markers.
         * @private
         */
        _flagParentsIconsNeedUpdate: function(layers) {
          var id, parent;
          for (id in layers) {
            parent = layers[id].__parent;
            while (parent) {
              parent._iconNeedsUpdate = true;
              parent = parent.__parent;
            }
          }
        },
        /**
         * Re-draws the icon of the supplied markers.
         * To be used in singleMarkerMode only.
         * @param layers Array(L.Marker)|Map(L.Marker) list of markers.
         * @private
         */
        _refreshSingleMarkerModeMarkers: function(layers) {
          var id, layer;
          for (id in layers) {
            layer = layers[id];
            if (this.hasLayer(layer)) {
              layer.setIcon(this._overrideMarkerIcon(layer));
            }
          }
        }
      });
      L.Marker.include({
        /**
         * Updates the given options in the marker's icon and refreshes the marker.
         * @param options map object of icon options.
         * @param directlyRefreshClusters boolean (optional) true to trigger
         * MCG.refreshClustersOf() right away with this single marker.
         * @returns {L.Marker}
         */
        refreshIconOptions: function(options, directlyRefreshClusters) {
          var icon = this.options.icon;
          L.setOptions(icon, options);
          this.setIcon(icon);
          if (directlyRefreshClusters && this.__parent) {
            this.__parent._group.refreshClusters(this);
          }
          return this;
        }
      });
      exports4.MarkerClusterGroup = MarkerClusterGroup;
      exports4.MarkerCluster = MarkerCluster;
      Object.defineProperty(exports4, "__esModule", { value: true });
    });
  })(leaflet_markerclusterSrc, leaflet_markerclusterSrc.exports);
  const getHtmlElement = (arg) => {
    const elem = typeof arg === "string" ? document.querySelector(arg) : arg;
    if (!elem) {
      throw new Error(`Element with selector ${arg} not found`);
    }
    return elem;
  };
  const PIN_COLOR_DEFAULT = "#2a81cb";
  const EARTH_RADIUS = 63710088e-1;
  const PIN_VIEW_WIDTH = 25;
  const PIN_VIEW_HEIGHT = 41;
  function isObj(value) {
    return typeof value === "object" && value !== null && !Array.isArray(value);
  }
  function isNonEmptyString(value) {
    return typeof value === "string" && value.length > 0;
  }
  function getPath(props, path) {
    if (props == null || path === "") return void 0;
    let current = props;
    for (const part of path.split(".")) {
      if (current == null || typeof current !== "object") return void 0;
      current = current[part];
    }
    return current;
  }
  function stringifyScalar(value) {
    if (value == null) return "";
    return typeof value === "string" ? value : String(value);
  }
  function renderValue(value) {
    if (value == null) return "";
    if (Array.isArray(value)) {
      return value.map((item) => escapeHtmlOfValue(item)).join(", ");
    }
    return escapeHtmlOfValue(value);
  }
  function escapeHtmlOfValue(value) {
    return escapeHtml(stringifyScalar(value));
  }
  function escapeHtml(text) {
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function renderTemplate(template, props) {
    let result = "";
    let i = 0;
    while (i < template.length) {
      const ch = template[i];
      if (ch === "{") {
        if (template[i + 1] === "{") {
          result += "{";
          i += 2;
          continue;
        }
        const close = template.indexOf("}", i + 1);
        if (close === -1) {
          result += ch;
          i += 1;
          continue;
        }
        const expression = template.slice(i + 1, close);
        result += renderValue(getPath(props, expression));
        i = close + 1;
        continue;
      }
      if (ch === "}") {
        if (template[i + 1] === "}") {
          result += "}";
          i += 2;
        } else {
          result += ch;
          i += 1;
        }
        continue;
      }
      result += ch;
      i += 1;
    }
    return result;
  }
  function comparisonKeys(value) {
    if (value == null) return [];
    if (Array.isArray(value)) {
      return value.filter((item) => item != null).map((item) => stringifyScalar(item));
    }
    return [stringifyScalar(value)];
  }
  function toFiniteNumber(value) {
    if (typeof value === "number") {
      return Number.isFinite(value) ? value : void 0;
    }
    if (typeof value === "string" && value.trim() !== "") {
      const number = Number(value);
      return Number.isFinite(number) ? number : void 0;
    }
    return void 0;
  }
  function resolveColor(colorBy, props, fallback) {
    const raw = getPath(props, colorBy.property);
    const fallbackColor = colorBy.fallback ?? fallback;
    if (colorBy.mode === "categories") {
      for (const key of comparisonKeys(raw)) {
        const match = colorBy.categories.find((category) => stringifyScalar(category.value) === key);
        if (match) return match.color;
      }
      return fallbackColor;
    }
    const number = toFiniteNumber(raw);
    if (number === void 0) return fallbackColor;
    for (const range of colorBy.ranges) {
      if (range.min !== void 0 && number < range.min) continue;
      if (range.max !== void 0 && number >= range.max) continue;
      return range.color;
    }
    return fallbackColor;
  }
  function formatDeNumber(value) {
    return new Intl.NumberFormat("de-DE").format(value);
  }
  function colorLegend(colorBy) {
    if (colorBy.mode === "categories") {
      return colorBy.categories.map((category) => ({
        color: category.color,
        label: category.label ?? stringifyScalar(category.value)
      }));
    }
    return colorBy.ranges.map((range) => {
      const min = range.min;
      const max = range.max;
      let label;
      if (range.label !== void 0) {
        label = range.label;
      } else if (min !== void 0 && max !== void 0) {
        label = `${formatDeNumber(min)} – ${formatDeNumber(max)}`;
      } else if (min !== void 0) {
        label = `ab ${formatDeNumber(min)}`;
      } else if (max !== void 0) {
        label = `unter ${formatDeNumber(max)}`;
      } else {
        label = "";
      }
      return { color: range.color, label };
    });
  }
  function resolveIcon(layer, props) {
    if (layer.iconBy) {
      const raw = getPath(props, layer.iconBy.property);
      for (const key of comparisonKeys(raw)) {
        const match = Object.hasOwn(layer.iconBy.values, key) ? layer.iconBy.values[key] : void 0;
        if (match) return match;
      }
    }
    return layer.icon ?? { type: "pin" };
  }
  function sanitizePinColor(color) {
    if (typeof color !== "string" || color.length === 0) return PIN_COLOR_DEFAULT;
    return /^[#a-zA-Z0-9(),.% -]+$/.test(color) ? color : PIN_COLOR_DEFAULT;
  }
  function pinIcon(icon) {
    const height = typeof icon.size === "number" && icon.size > 0 ? icon.size : 41;
    const width = Math.round(height * PIN_VIEW_WIDTH / PIN_VIEW_HEIGHT);
    const color = sanitizePinColor(icon.color);
    const glyph = escapeHtml(
      Array.from(String(icon.glyph ?? "")).slice(0, 2).join("")
    );
    const html = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${PIN_VIEW_WIDTH} ${PIN_VIEW_HEIGHT}"><path d="M12.5 0C5.6 0 0 5.6 0 12.5c0 8.9 12.5 28.5 12.5 28.5S25 21.4 25 12.5C25 5.6 19.4 0 12.5 0z" fill="${color}" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/><circle cx="12.5" cy="12.5" r="7.5" fill="#fff"/><text x="12.5" y="15.75" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="bold" fill="${color}">${glyph}</text></svg>`;
    return {
      html,
      size: [width, height],
      anchor: [width / 2, height],
      popupAnchor: [0, -height]
    };
  }
  function firstChildByName(node, localName) {
    const children = node.children;
    for (let i = 0; i < children.length; i++) {
      const child = children.item(i);
      if (child && child.localName === localName) return child;
    }
    return void 0;
  }
  function childElementsByName(node, localName) {
    const elements = [];
    const children = node.children;
    for (let i = 0; i < children.length; i++) {
      const child = children.item(i);
      if (child && child.localName === localName) elements.push(child);
    }
    return elements;
  }
  function parseGpxPoint(node) {
    const latRaw = node.getAttribute("lat");
    const lonRaw = node.getAttribute("lon");
    if (typeof latRaw !== "string" || latRaw.trim() === "") return null;
    if (typeof lonRaw !== "string" || lonRaw.trim() === "") return null;
    const lat = Number(latRaw);
    const lon = Number(lonRaw);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
    const ele = firstChildByName(node, "ele");
    if (ele) {
      const eleText = ele.textContent;
      if (eleText !== null && eleText.trim() !== "") {
        const elevation = Number(eleText);
        if (Number.isFinite(elevation)) return [lon, lat, elevation];
      }
    }
    return [lon, lat];
  }
  function readGpxProperties(node, names) {
    const properties = {};
    for (const name of names) {
      const child = firstChildByName(node, name);
      if (child) properties[name] = child.textContent ?? "";
    }
    return properties;
  }
  function gpxTrackToFeature(track) {
    const segments = childElementsByName(track, "trkseg").map((segment) => {
      return childElementsByName(segment, "trkpt").map((point) => parseGpxPoint(point)).filter((point) => point !== null);
    });
    let geometry;
    if (segments.length > 1) {
      geometry = { type: "MultiLineString", coordinates: segments };
    } else {
      geometry = {
        type: "LineString",
        coordinates: segments.length === 1 ? segments[0] : []
      };
    }
    return {
      type: "Feature",
      geometry,
      properties: readGpxProperties(track, ["name", "desc"])
    };
  }
  function gpxRouteToFeature(route) {
    const coordinates = childElementsByName(route, "rtept").map((point) => parseGpxPoint(point)).filter((point) => point !== null);
    return {
      type: "Feature",
      geometry: { type: "LineString", coordinates },
      properties: readGpxProperties(route, ["name", "desc"])
    };
  }
  function gpxWaypointToFeature(waypoint) {
    const coordinates = parseGpxPoint(waypoint);
    if (!coordinates) return null;
    return {
      type: "Feature",
      geometry: { type: "Point", coordinates },
      properties: readGpxProperties(waypoint, ["name", "desc", "sym"])
    };
  }
  function gpxToGeoJson(xml) {
    const doc = new DOMParser().parseFromString(xml, "application/xml");
    if (doc.getElementsByTagName("parsererror").length > 0) {
      throw new Error("Invalid GPX: XML parser error");
    }
    const root = doc.documentElement;
    if (!root || root.localName !== "gpx") {
      throw new Error("Invalid GPX: missing <gpx> root");
    }
    const features = [];
    for (let i = 0; i < root.children.length; i++) {
      const child = root.children.item(i);
      if (!child) continue;
      if (child.localName === "trk") {
        features.push(gpxTrackToFeature(child));
      } else if (child.localName === "rte") {
        features.push(gpxRouteToFeature(child));
      } else if (child.localName === "wpt") {
        const waypointFeature = gpxWaypointToFeature(child);
        if (waypointFeature) features.push(waypointFeature);
      }
    }
    return { type: "FeatureCollection", features };
  }
  function haversineMeters(lon1, lat1, lon2, lat2) {
    const toRadians = Math.PI / 180;
    const dLat = (lat2 - lat1) * toRadians;
    const dLon = (lon2 - lon1) * toRadians;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * toRadians) * Math.cos(lat2 * toRadians) * Math.sin(dLon / 2) ** 2;
    const root = Math.sqrt(Math.min(1, a));
    return 2 * EARTH_RADIUS * Math.asin(root);
  }
  function elevationProfile(lines) {
    const flat = [];
    for (let lineIndex = 0; lineIndex < lines.length; lineIndex++) {
      const line = lines[lineIndex];
      if (!Array.isArray(line)) continue;
      for (const coords of line) {
        if (coords.length < 3) return null;
        const elevation = coords[2];
        if (typeof elevation !== "number" || !Number.isFinite(elevation)) {
          return null;
        }
        flat.push({
          coords: [coords[0], coords[1], elevation],
          line: lineIndex
        });
      }
    }
    if (flat.length < 2) return null;
    let distance = 0;
    let ascent = 0;
    let descent = 0;
    let min = flat[0].coords[2];
    let max = flat[0].coords[2];
    const points = [{ distance: 0, elevation: flat[0].coords[2] }];
    for (let i = 1; i < flat.length; i++) {
      const previous = flat[i - 1];
      const current = flat[i];
      if (previous.line === current.line) {
        distance += haversineMeters(previous.coords[0], previous.coords[1], current.coords[0], current.coords[1]);
      }
      const elevationDifference = current.coords[2] - previous.coords[2];
      if (elevationDifference > 0) {
        ascent += elevationDifference;
      } else if (elevationDifference < 0) {
        descent += -elevationDifference;
      }
      if (current.coords[2] < min) min = current.coords[2];
      if (current.coords[2] > max) max = current.coords[2];
      points.push({ distance, elevation: current.coords[2] });
    }
    return { points, length: distance, ascent, descent, min, max };
  }
  function collectDistinctValues(value, into) {
    if (value == null) return;
    if (Array.isArray(value)) {
      for (const item of value) collectDistinctValues(item, into);
      return;
    }
    const stringValue = stringifyScalar(value);
    if (stringValue !== "") into.add(stringValue);
  }
  function distinctValues(features, property) {
    const values = /* @__PURE__ */ new Set();
    for (const feature of features) {
      const raw = getPath(feature.properties, property);
      collectDistinctValues(raw, values);
    }
    return Array.from(values).sort((a, b) => a.localeCompare(b, "de"));
  }
  function containsFilterMatch(value, selected) {
    if (value == null) return false;
    if (Array.isArray(value)) {
      return value.some((item) => containsFilterMatch(item, selected));
    }
    return selected.has(stringifyScalar(value));
  }
  function matchesFilter(props, property, selected) {
    return containsFilterMatch(getPath(props, property), selected);
  }
  function imageToLatLng(position, imageHeight) {
    const lat = imageHeight - position[1];
    const lng = position[0];
    return [lat, lng];
  }
  function latLngToImage(latLng, imageHeight) {
    const x = latLng[1];
    const y = imageHeight - latLng[0];
    return [x, y];
  }
  function validateMapDef(input) {
    const errors = [];
    if (!isObj(input)) {
      errors.push("(root): expected an object");
      return { ok: false, errors };
    }
    const root = input;
    const seenIds = /* @__PURE__ */ new Map();
    if (root.version !== 1) errors.push("version: must be 1");
    if (!isNonEmptyString(root.id)) {
      errors.push("id: must be a non-empty string");
    }
    let kind = "geo";
    if (root.kind !== void 0) {
      if (root.kind === "geo" || root.kind === "image") {
        kind = root.kind;
      } else {
        errors.push('kind: must be "geo" or "image"');
      }
    }
    function checkDuplicateId(id, path) {
      const existing = seenIds.get(id);
      if (existing !== void 0) {
        errors.push(`${path}: duplicate id "${id}"`);
      } else {
        seenIds.set(id, path);
      }
    }
    function validateImageBasemap(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      if (value.type !== "image") {
        errors.push(`${path}.type: must be "image"`);
      }
      if (isNonEmptyString(value.id)) {
        checkDuplicateId(value.id, `${path}.id`);
      } else {
        errors.push(`${path}.id: must be a non-empty string`);
      }
      if (!isNonEmptyString(value.url)) {
        errors.push(`${path}.url: must be a non-empty string`);
      }
      if (typeof value.width !== "number" || !Number.isFinite(value.width) || value.width <= 0) {
        errors.push(`${path}.width: must be a positive number`);
      }
      if (typeof value.height !== "number" || !Number.isFinite(value.height) || value.height <= 0) {
        errors.push(`${path}.height: must be a positive number`);
      }
    }
    function validateXyzBasemap(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      if (value.type !== "xyz") {
        errors.push(`${path}.type: must be "xyz"`);
      }
      if (isNonEmptyString(value.id)) {
        checkDuplicateId(value.id, `${path}.id`);
      } else {
        errors.push(`${path}.id: must be a non-empty string`);
      }
      if (!isNonEmptyString(value.label)) {
        errors.push(`${path}.label: must be a non-empty string`);
      }
      if (!isNonEmptyString(value.url)) {
        errors.push(`${path}.url: must be a non-empty string`);
      }
    }
    function validateGeoJsonData(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      if (value.type !== "FeatureCollection") {
        errors.push(`${path}.type: must be "FeatureCollection"`);
      }
      if (!Array.isArray(value.features)) {
        errors.push(`${path}.features: must be an array`);
      }
    }
    function validateSource(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      const type = value.type;
      if (type !== "geojson" && type !== "gpx") {
        errors.push(`${path}.type: must be "geojson" or "gpx"`);
      }
      const hasUrl = value.url !== void 0;
      const hasData = value.data !== void 0;
      if (hasUrl === hasData) {
        errors.push(`${path}: exactly one of url or data is required`);
      } else if (hasUrl) {
        if (!isNonEmptyString(value.url)) {
          errors.push(`${path}.url: must be a non-empty string`);
        }
      } else if (type === "geojson") {
        validateGeoJsonData(value.data, `${path}.data`);
      } else if (type === "gpx") {
        if (typeof value.data !== "string") {
          errors.push(`${path}.data: must be a string`);
        }
      }
    }
    function validateColorBy(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      const mode = value.mode;
      if (mode === "categories") {
        if (!Array.isArray(value.categories)) {
          errors.push(`${path}.categories: expected an array`);
        }
      } else if (mode === "ranges") {
        if (!Array.isArray(value.ranges)) {
          errors.push(`${path}.ranges: expected an array`);
        }
      } else {
        errors.push(`${path}.mode: must be "categories" or "ranges"`);
      }
    }
    function validateLayer(value, path) {
      if (!isObj(value)) {
        errors.push(`${path}: expected an object`);
        return;
      }
      const type = value.type;
      if (type !== "markers" && type !== "areas" && type !== "route") {
        errors.push(`${path}.type: must be "markers", "areas" or "route"`);
      }
      if (isNonEmptyString(value.id)) {
        checkDuplicateId(value.id, `${path}.id`);
      } else {
        errors.push(`${path}.id: must be a non-empty string`);
      }
      validateSource(value.source, `${path}.source`);
      if (value.colorBy !== void 0) {
        validateColorBy(value.colorBy, `${path}.colorBy`);
      }
    }
    if (Array.isArray(root.basemaps)) {
      const basemaps = root.basemaps;
      if (kind === "image") {
        if (basemaps.length !== 1) {
          errors.push("basemaps: image map requires exactly one image basemap");
        }
        basemaps.forEach((basemap, index) => {
          validateImageBasemap(basemap, `basemaps[${index}]`);
        });
      } else {
        if (basemaps.length < 1) {
          errors.push("basemaps: geo map requires at least one xyz basemap");
        }
        basemaps.forEach((basemap, index) => {
          validateXyzBasemap(basemap, `basemaps[${index}]`);
        });
      }
    } else {
      errors.push("basemaps: expected an array");
    }
    if (Array.isArray(root.layers)) {
      const layers = root.layers;
      layers.forEach((layer, index) => {
        validateLayer(layer, `layers[${index}]`);
      });
    } else {
      errors.push("layers: expected an array");
    }
    if (root.view !== void 0) {
      if (!isObj(root.view)) {
        errors.push("view: expected an object");
      } else if (root.view.center !== void 0) {
        const center = root.view.center;
        if (!Array.isArray(center) || center.length !== 2) {
          errors.push("view.center: must be an array of two finite numbers");
        } else {
          const first = center[0];
          const second = center[1];
          if (typeof first !== "number" || !Number.isFinite(first) || typeof second !== "number" || !Number.isFinite(second)) {
            errors.push("view.center: must be an array of two finite numbers");
          }
        }
      }
    }
    if (errors.length > 0) return { ok: false, errors };
    return { ok: true, def: input };
  }
  const BASE_STYLE = {
    color: "#3388ff",
    weight: 2,
    opacity: 1,
    fillColor: "#3388ff",
    fillOpacity: 0.3
  };
  const SAFE_COLOR = /^[#a-zA-Z0-9(),.% -]+$/;
  function createAreaLayer(def, data, ctx) {
    const label = def.label ?? def.id;
    const features = data.features.filter(
      (feature) => feature.geometry !== null && (feature.geometry.type === "Polygon" || feature.geometry.type === "MultiPolygon")
    );
    const collection = {
      type: "FeatureCollection",
      features
    };
    const baseStyle = { ...BASE_STYLE, ...def.style ?? {} };
    const legendStrokeColor = SAFE_COLOR.test(baseStyle.color ?? "") ? baseStyle.color : "#3388ff";
    const legendFillOpacity = baseStyle.fillOpacity ?? 0.3;
    const styles = /* @__PURE__ */ new WeakMap();
    const featureStyle = (props) => {
      const style = { ...baseStyle };
      if (def.colorBy) {
        const resolved = resolveColor(def.colorBy, props, style.fillColor ?? "#3388ff");
        if (resolved) style.fillColor = resolved;
      }
      return style;
    };
    const legendSwatch = (color) => {
      const safe = SAFE_COLOR.test(color) ? color : "";
      const background = safe ? `background:${safe};` : "";
      return `<span style="display:inline-block;width:14px;height:14px;${background}opacity:${legendFillOpacity};border:1px solid ${legendStrokeColor}"></span>`;
    };
    const geo = L$1.geoJSON(collection, {
      attribution: def.attribution,
      coordsToLatLng: (coords) => ctx.toLatLng(coords),
      style: (feature) => featureStyle((feature == null ? void 0 : feature.properties) ?? null),
      onEachFeature: (feature, layer) => {
        const path = layer;
        const props = feature.properties ?? null;
        const ownStyle = featureStyle(props);
        styles.set(path, ownStyle);
        if (def.tooltip) {
          const text = renderTemplate(def.tooltip, props).trim();
          if (text) layer.bindTooltip(text, { direction: "top", sticky: true });
        }
        if (def.popup) {
          const text = renderTemplate(def.popup, props).trim();
          if (text) layer.bindPopup(text);
        }
        if (def.hoverStyle !== false) {
          layer.on("mouseover", () => {
            const hover = def.hoverStyle ?? { weight: 4 };
            path.setStyle({ ...styles.get(path) ?? ownStyle, ...hover });
            path.bringToFront();
          });
          layer.on("mouseout", () => {
            const own = styles.get(path);
            if (own) path.setStyle(own);
          });
        }
      }
    });
    let onShow;
    let onHide;
    if (def.colorBy && def.legend !== false) {
      const title = def.legend && typeof def.legend === "object" ? def.legend.title ?? label : label;
      const rows = colorLegend(def.colorBy).map((row) => ({
        swatchHtml: legendSwatch(row.color),
        label: row.label
      }));
      onShow = () => ctx.setLegend(def.id, title, rows);
      onHide = () => ctx.clearLegend(def.id);
    }
    return {
      id: def.id,
      label,
      leafletLayer: geo,
      bounds: () => {
        const bounds = geo.getBounds();
        return bounds.isValid() ? bounds : null;
      },
      onShow,
      onHide
    };
  }
  class LegendControl extends L$1.Control {
    constructor(messages) {
      super({ position: "bottomright" });
      __publicField(this, "container");
      __publicField(this, "sections", /* @__PURE__ */ new Map());
      this.messages = messages;
    }
    onAdd() {
      this.container = L$1.DomUtil.create("div", "mapmagic-legend leaflet-bar");
      this.container.setAttribute("role", "region");
      this.container.setAttribute("aria-label", this.messages.legend);
      L$1.DomEvent.disableClickPropagation(this.container);
      L$1.DomEvent.disableScrollPropagation(this.container);
      this.render();
      return this.container;
    }
    setSection(id, title, rows) {
      this.sections.set(id, { title, rows });
      this.render();
    }
    clearSection(id) {
      this.sections.delete(id);
      this.render();
    }
    render() {
      if (!this.container) return;
      this.container.hidden = this.sections.size === 0;
      this.container.innerHTML = [...this.sections.values()].map(
        ({ title, rows }) => `<div class="mapmagic-legend__section"><div class="mapmagic-legend__title">${escapeHtml(title)}</div><ul>` + rows.map(
          (row) => `<li><span class="mapmagic-legend__swatch" aria-hidden="true">${row.swatchHtml}</span>${escapeHtml(row.label)}</li>`
        ).join("") + "</ul></div>"
      ).join("");
    }
  }
  class SlotControl extends L$1.Control {
    constructor(className, position) {
      super({ position });
      __publicField(this, "container");
      __publicField(this, "slots", /* @__PURE__ */ new Map());
      this.container = L$1.DomUtil.create("div", className);
      L$1.DomEvent.disableClickPropagation(this.container);
      L$1.DomEvent.disableScrollPropagation(this.container);
    }
    onAdd() {
      return this.container;
    }
    slot(id) {
      let element = this.slots.get(id);
      if (!element) {
        element = L$1.DomUtil.create("div", "mapmagic-filter leaflet-bar", this.container);
        element.dataset.layer = id;
        this.slots.set(id, element);
      }
      return element;
    }
  }
  class FullscreenControl extends L$1.Control {
    constructor(root, messages) {
      super({ position: "topleft" });
      this.root = root;
      this.messages = messages;
    }
    onAdd(map) {
      const bar = L$1.DomUtil.create("div", "leaflet-bar mapmagic-fullscreen");
      const button = L$1.DomUtil.create("a", "", bar);
      button.href = "#";
      button.setAttribute("role", "button");
      const update = () => {
        const active = document.fullscreenElement === this.root;
        const label = active ? this.messages.fullscreenExit : this.messages.fullscreen;
        button.title = label;
        button.setAttribute("aria-label", label);
        button.innerHTML = active ? "&#x2715;" : "&#x26F6;";
        this.root.classList.toggle("mapmagic--fullscreen", active);
        map.invalidateSize();
      };
      L$1.DomEvent.on(button, "click", (event) => {
        var _a, _b;
        L$1.DomEvent.preventDefault(event);
        if (document.fullscreenElement === this.root) {
          void document.exitFullscreen();
        } else {
          void ((_b = (_a = this.root).requestFullscreen) == null ? void 0 : _b.call(_a));
        }
      });
      document.addEventListener("fullscreenchange", update);
      update();
      return bar;
    }
  }
  function installCtrlScroll(map, messages) {
    const container = map.getContainer();
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
    const hint = L$1.DomUtil.create("div", "mapmagic-scroll-hint", container);
    hint.textContent = isMac ? messages.scrollHintMac : messages.scrollHint;
    hint.setAttribute("aria-hidden", "true");
    let timer;
    const onWheel = (event) => {
      if (event.ctrlKey || event.metaKey) {
        hint.classList.remove("is-visible");
        return;
      }
      event.stopImmediatePropagation();
      hint.classList.add("is-visible");
      window.clearTimeout(timer);
      timer = window.setTimeout(() => hint.classList.remove("is-visible"), 1200);
    };
    container.addEventListener("wheel", onWheel, { capture: true });
    return () => {
      container.removeEventListener("wheel", onWheel, { capture: true });
      window.clearTimeout(timer);
    };
  }
  const iconCache = /* @__PURE__ */ new Map();
  function toLeafletIcon(icon) {
    const key = JSON.stringify(icon);
    const cached = iconCache.get(key);
    if (cached) return cached;
    let rendered;
    if (icon.type === "pin") {
      const pin = pinIcon(icon);
      rendered = L$1.divIcon({
        html: pin.html,
        className: "mapmagic-pin",
        iconSize: pin.size,
        iconAnchor: pin.anchor,
        popupAnchor: pin.popupAnchor,
        tooltipAnchor: [0, -pin.size[1]]
      });
    } else {
      const anchor = icon.anchor ?? [icon.size[0] / 2, icon.size[1]];
      rendered = L$1.icon({
        iconUrl: icon.url,
        iconSize: icon.size,
        iconAnchor: anchor,
        popupAnchor: [0, -anchor[1]],
        tooltipAnchor: [0, -anchor[1]]
      });
    }
    iconCache.set(key, rendered);
    return rendered;
  }
  function iconSwatchHtml(icon) {
    if (icon.type === "pin") {
      return pinIcon({ ...icon, size: 20 }).html;
    }
    const maxSide = Math.max(icon.size[0], icon.size[1]);
    const width = Math.round(icon.size[0] / maxSide * 20);
    const height = Math.round(icon.size[1] / maxSide * 20);
    return `<img src="${escapeHtml(icon.url)}" alt="" width="${width}" height="${height}">`;
  }
  function createContainer(def) {
    if (def.cluster) {
      const cluster = def.cluster === true ? {} : def.cluster;
      const group2 = L$1.markerClusterGroup({
        attribution: def.attribution,
        maxClusterRadius: cluster.radius ?? 60,
        disableClusteringAtZoom: cluster.disableAtZoom,
        showCoverageOnHover: false,
        chunkedLoading: true
      });
      return {
        layer: group2,
        clearLayers: () => group2.clearLayers(),
        addLayers: (layers) => void group2.addLayers(layers)
      };
    }
    const group = L$1.featureGroup([], { attribution: def.attribution });
    return {
      layer: group,
      clearLayers: () => group.clearLayers(),
      addLayers: (layers) => layers.forEach((layer) => group.addLayer(layer))
    };
  }
  function textOf(html) {
    return (new DOMParser().parseFromString(html, "text/html").body.textContent ?? "").trim();
  }
  function buildFilterBox(def, data, records, container, ctx, label) {
    const filter = def.filter;
    const property = filter.property;
    const options = filter.options ?? distinctValues(data.features, property).map((value) => ({ value }));
    const box = ctx.filterSlot(def.id);
    box.replaceChildren();
    const fieldset = document.createElement("fieldset");
    fieldset.className = "mapmagic-filter__fieldset";
    const legend = document.createElement("legend");
    legend.textContent = filter.label ?? label;
    fieldset.append(legend);
    const optionsWrap = document.createElement("div");
    optionsWrap.className = "mapmagic-filter__options";
    const checkboxes = [];
    for (const option of options) {
      const optionLabel = document.createElement("label");
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.checked = true;
      checkbox.value = option.value;
      const text = document.createElement("span");
      text.textContent = option.label ?? option.value;
      optionLabel.append(checkbox, text);
      optionsWrap.append(optionLabel);
      checkboxes.push(checkbox);
    }
    const actions = document.createElement("div");
    actions.className = "mapmagic-filter__actions";
    const allButton = document.createElement("button");
    allButton.type = "button";
    allButton.textContent = ctx.messages.filterAll;
    const noneButton = document.createElement("button");
    noneButton.type = "button";
    noneButton.textContent = ctx.messages.filterNone;
    actions.append(allButton, noneButton);
    const apply = () => {
      const selected = /* @__PURE__ */ new Set();
      for (const checkbox of checkboxes) {
        if (checkbox.checked) selected.add(checkbox.value);
      }
      container.clearLayers();
      container.addLayers(
        records.filter((record) => matchesFilter(record.props, property, selected)).map((record) => record.marker)
      );
    };
    fieldset.addEventListener("change", apply);
    allButton.addEventListener("click", () => {
      checkboxes.forEach((checkbox) => {
        checkbox.checked = true;
      });
      apply();
    });
    noneButton.addEventListener("click", () => {
      checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
      });
      apply();
    });
    fieldset.append(optionsWrap, actions);
    box.append(fieldset);
    return box;
  }
  function createMarkerLayer(def, data, ctx) {
    const label = def.label ?? def.id;
    const records = [];
    for (const feature of data.features) {
      if (!feature.geometry) continue;
      const points = feature.geometry.type === "Point" ? [feature.geometry.coordinates] : feature.geometry.type === "MultiPoint" ? feature.geometry.coordinates : [];
      if (points.length === 0) continue;
      const icon = toLeafletIcon(resolveIcon(def, feature.properties));
      const tooltipHtml = def.tooltip ? renderTemplate(def.tooltip, feature.properties) : "";
      const popupHtml = def.popup ? renderTemplate(def.popup, feature.properties) : "";
      for (const point of points) {
        const marker = L$1.marker(ctx.toLatLng(point), { icon, keyboard: true });
        if (tooltipHtml.trim() !== "") {
          marker.bindTooltip(tooltipHtml, { direction: "top", sticky: false });
          const ariaLabel = textOf(tooltipHtml);
          marker.on("add", () => {
            var _a;
            (_a = marker.getElement()) == null ? void 0 : _a.setAttribute("aria-label", ariaLabel);
          });
        }
        if (popupHtml.trim() !== "") {
          marker.bindPopup(popupHtml);
        }
        records.push({ marker, props: feature.properties });
      }
    }
    const container = createContainer(def);
    container.addLayers(records.map((record) => record.marker));
    let filterBox = null;
    if (def.filter) {
      filterBox = buildFilterBox(def, data, records, container, ctx, label);
      filterBox.hidden = true;
    }
    const iconBy = def.iconBy;
    const showLegend = Boolean(def.legend && iconBy);
    const legendTitle = typeof def.legend === "object" ? def.legend.title ?? label : label;
    const legendRows = iconBy && showLegend ? Object.entries(iconBy.values).map(([key, entry]) => ({
      swatchHtml: iconSwatchHtml(entry),
      label: entry.label ?? key
    })) : [];
    return {
      id: def.id,
      label,
      leafletLayer: container.layer,
      bounds: () => {
        if (records.length === 0) return null;
        const bounds = L$1.latLngBounds(records.map((record) => record.marker.getLatLng()));
        return bounds.isValid() ? bounds : null;
      },
      onShow: () => {
        if (filterBox) filterBox.hidden = false;
        if (showLegend && iconBy) {
          ctx.setLegend(def.id, legendTitle, legendRows);
        }
      },
      onHide: () => {
        if (filterBox) filterBox.hidden = true;
        if (showLegend) {
          ctx.clearLegend(def.id);
        }
      }
    };
  }
  const defaultMessages = {
    scrollHint: "Zum Zoomen Strg + Mausrad verwenden",
    scrollHintMac: "Zum Zoomen ⌘ + Mausrad verwenden",
    fullscreen: "Vollbild",
    fullscreenExit: "Vollbild beenden",
    legend: "Legende",
    filterAll: "Alle",
    filterNone: "Keine",
    elevationLength: "Länge",
    elevationAscent: "Aufstieg",
    elevationDescent: "Abstieg",
    elevationAxis: "Höhe (m)",
    layerError: "Die Ebene „{label}“ konnte nicht geladen werden."
  };
  const DEFAULT_LINE_STYLE = {
    color: "#e74c3c",
    weight: 4,
    opacity: 0.9
  };
  const DEFAULT_START_ICON = { type: "pin", color: "#27ae60" };
  const DEFAULT_END_ICON = { type: "pin", color: "#c0392b" };
  const DEFAULT_WAYPOINT_ICON = { type: "pin", color: "#7f8c8d", size: 30 };
  const LOOP_TOLERANCE_METERS = 25;
  const createRouteLayer = (def, data, ctx) => {
    var _a;
    const group = L$1.featureGroup([], { attribution: def.attribution });
    const lineStyle = { ...DEFAULT_LINE_STYLE, ...def.style };
    const lineCoords = [];
    const bindFeatureInteraction = (layer, feature) => {
      if (def.tooltip) {
        const tooltip = renderTemplate(def.tooltip, feature.properties);
        if (tooltip.trim()) {
          layer.bindTooltip(tooltip, { direction: "top", sticky: false });
        }
      }
      if (def.popup) {
        const popup = renderTemplate(def.popup, feature.properties);
        if (popup.trim()) {
          layer.bindPopup(popup);
        }
      }
    };
    const waypointIcon = def.waypoints !== false ? typeof def.waypoints === "object" ? def.waypoints.icon ?? DEFAULT_WAYPOINT_ICON : DEFAULT_WAYPOINT_ICON : false;
    data.features.forEach((feature) => {
      if (!feature.geometry) return;
      const geometry = feature.geometry;
      if (geometry.type === "LineString" && geometry.coordinates.length > 0) {
        lineCoords.push(geometry.coordinates);
        const layer = L$1.polyline(
          geometry.coordinates.map((position) => ctx.toLatLng(position)),
          lineStyle
        ).addTo(group);
        bindFeatureInteraction(layer, feature);
        return;
      }
      if (geometry.type === "MultiLineString") {
        geometry.coordinates.forEach((coordinates) => {
          if (coordinates.length === 0) return;
          lineCoords.push(coordinates);
          const layer = L$1.polyline(
            coordinates.map((position) => ctx.toLatLng(position)),
            lineStyle
          ).addTo(group);
          bindFeatureInteraction(layer, feature);
        });
        return;
      }
      if (geometry.type === "Point" && waypointIcon) {
        const marker = L$1.marker(ctx.toLatLng(geometry.coordinates), {
          icon: toLeafletIcon(waypointIcon)
        }).addTo(group);
        bindFeatureInteraction(marker, feature);
      }
    });
    if (lineCoords.length > 0) {
      const firstLine = lineCoords[0];
      const lastLine = lineCoords[lineCoords.length - 1];
      if (firstLine.length > 0 && def.startIcon !== false) {
        L$1.marker(ctx.toLatLng(firstLine[0]), {
          icon: toLeafletIcon(def.startIcon ?? DEFAULT_START_ICON)
        }).addTo(group);
      }
      const start = firstLine[0];
      const end = lastLine[lastLine.length - 1];
      const isLoop = start !== void 0 && end !== void 0 && def.startIcon !== false && ctx.map.distance(ctx.toLatLng(start), ctx.toLatLng(end)) < LOOP_TOLERANCE_METERS;
      if (lastLine.length > 0 && def.endIcon !== false && !isLoop) {
        L$1.marker(ctx.toLatLng(lastLine[lastLine.length - 1]), {
          icon: toLeafletIcon(def.endIcon ?? DEFAULT_END_ICON)
        }).addTo(group);
      }
    }
    let panel = null;
    let hoverLine = null;
    let hoverMarker = null;
    const removeHoverMarker = () => {
      if (hoverMarker) {
        hoverMarker.removeFrom(ctx.map);
        hoverMarker = null;
      }
    };
    if (def.elevationProfile && lineCoords.length > 0) {
      const profile = elevationProfile(lineCoords);
      if (profile) {
        const routeColor = lineStyle.color ?? "#e74c3c";
        const profileLatLngs = [];
        lineCoords.forEach((coordinates) => {
          coordinates.forEach((position) => profileLatLngs.push(ctx.toLatLng(position)));
        });
        panel = document.createElement("div");
        panel.className = "mapmagic-elevation";
        panel.hidden = true;
        const locale = document.documentElement.lang || "de-DE";
        const km = new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        const m = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
        const formatKm = (meters) => `${km.format(meters / 1e3)} km`;
        const formatMeters = (meters) => `${m.format(meters)} m`;
        const dl = document.createElement("dl");
        const addFact = (label, value) => {
          const dt = document.createElement("dt");
          dt.textContent = label;
          const dd = document.createElement("dd");
          dd.textContent = value;
          dl.append(dt, dd);
        };
        addFact(ctx.messages.elevationLength, formatKm(profile.length));
        addFact(ctx.messages.elevationAscent, formatMeters(profile.ascent));
        addFact(ctx.messages.elevationDescent, formatMeters(profile.descent));
        panel.append(dl);
        const summaryText = `${ctx.messages.elevationLength} ${formatKm(profile.length)}, ${ctx.messages.elevationAscent} ${formatMeters(profile.ascent)}, ${ctx.messages.elevationDescent} ${formatMeters(profile.descent)}`;
        const svgNS = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(svgNS, "svg");
        svg.setAttribute("viewBox", "0 0 600 160");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "160");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.setAttribute("role", "img");
        svg.setAttribute("aria-label", summaryText);
        const baseY = 160;
        const yMin = Math.floor(profile.min / 10) * 10;
        let yMax = Math.ceil(profile.max / 10) * 10;
        if (yMin === yMax) yMax = yMin + 10;
        const maxDistance = profile.length > 0 ? profile.length : ((_a = profile.points[profile.points.length - 1]) == null ? void 0 : _a.distance) ?? 0;
        const chartX = (distance) => maxDistance > 0 ? distance / maxDistance * 600 : 0;
        const chartY = (elevation) => baseY - (elevation - yMin) / (yMax - yMin) * baseY;
        const labels = [];
        for (let i = 0; i < 3; i += 1) {
          const elevation = yMin + (yMax - yMin) * i / 2;
          const y = chartY(elevation);
          const gridLine = document.createElementNS(svgNS, "line");
          gridLine.setAttribute("x1", "0");
          gridLine.setAttribute("x2", "600");
          gridLine.setAttribute("y1", String(y));
          gridLine.setAttribute("y2", String(y));
          gridLine.setAttribute("stroke", "#999");
          gridLine.setAttribute("stroke-width", "1");
          gridLine.setAttribute("vector-effect", "non-scaling-stroke");
          svg.append(gridLine);
          const label = document.createElement("span");
          label.className = "mapmagic-elevation__label";
          label.style.top = `${y / baseY * 100}%`;
          if (y < 14) label.classList.add("mapmagic-elevation__label--below");
          label.textContent = `${m.format(elevation)} m`;
          labels.push(label);
        }
        const pointStrings = profile.points.map(
          (point) => `${chartX(point.distance).toFixed(2)},${chartY(point.elevation).toFixed(2)}`
        );
        const areaPath = document.createElementNS(svgNS, "path");
        areaPath.setAttribute(
          "d",
          `M ${pointStrings[0].split(",")[0]} ${baseY} L ${pointStrings.join(" L ")} L ${pointStrings[pointStrings.length - 1].split(",")[0]} ${baseY} Z`
        );
        areaPath.setAttribute("fill", routeColor);
        areaPath.setAttribute("fill-opacity", "0.25");
        areaPath.setAttribute("stroke", "none");
        svg.append(areaPath);
        const linePath = document.createElementNS(svgNS, "path");
        linePath.setAttribute("d", `M ${pointStrings.join(" L ")}`);
        linePath.setAttribute("fill", "none");
        linePath.setAttribute("stroke", routeColor);
        linePath.setAttribute("stroke-width", "1.5");
        linePath.setAttribute("vector-effect", "non-scaling-stroke");
        svg.append(linePath);
        const hoverElement = document.createElementNS(svgNS, "line");
        hoverElement.setAttribute("x1", "0");
        hoverElement.setAttribute("x2", "0");
        hoverElement.setAttribute("y1", "0");
        hoverElement.setAttribute("y2", String(baseY));
        hoverElement.setAttribute("stroke", "#333");
        hoverElement.setAttribute("stroke-width", "1");
        hoverElement.setAttribute("stroke-dasharray", "3 3");
        hoverElement.style.display = "none";
        svg.append(hoverElement);
        hoverLine = hoverElement;
        svg.addEventListener("pointermove", (event) => {
          const rect = svg.getBoundingClientRect();
          if (rect.width <= 0 || rect.height <= 0 || profile.points.length === 0) return;
          const viewX = (event.clientX - rect.left) / rect.width * 600;
          const targetDistance = maxDistance > 0 ? viewX / 600 * maxDistance : 0;
          let nearestIndex = 0;
          let best = Number.POSITIVE_INFINITY;
          profile.points.forEach((point2, index) => {
            const delta = Math.abs(point2.distance - targetDistance);
            if (delta < best) {
              best = delta;
              nearestIndex = index;
            }
          });
          const point = profile.points[nearestIndex];
          const latlng = profileLatLngs[nearestIndex] ?? profileLatLngs[0];
          if (!point || !latlng) return;
          hoverElement.style.display = "block";
          hoverElement.setAttribute("x1", String(chartX(point.distance)));
          hoverElement.setAttribute("x2", String(chartX(point.distance)));
          if (!hoverMarker) {
            hoverMarker = L$1.circleMarker(latlng, {
              radius: 6,
              color: "#ffffff",
              weight: 2,
              fillColor: routeColor,
              fillOpacity: 1
            }).addTo(ctx.map);
          } else {
            hoverMarker.setLatLng(latlng);
          }
        });
        svg.addEventListener("pointerleave", () => {
          hoverElement.style.display = "none";
          removeHoverMarker();
        });
        const chart = document.createElement("div");
        chart.className = "mapmagic-elevation__chart";
        chart.append(svg, ...labels);
        panel.append(chart);
        ctx.panelSlot(def.id).replaceChildren(panel);
      }
    }
    return {
      id: def.id,
      label: def.label ?? def.id,
      leafletLayer: group,
      bounds: () => {
        const bounds = group.getBounds();
        return bounds.isValid() ? bounds : null;
      },
      onShow: () => {
        if (panel) panel.hidden = false;
      },
      onHide: () => {
        if (panel) panel.hidden = true;
        if (hoverLine) hoverLine.style.display = "none";
        removeHoverMarker();
      }
    };
  };
  const GEO_DECIMALS = 6;
  const FALLBACK_CENTER = [51.16, 10.45];
  const FALLBACK_ZOOM = 6;
  async function createMap(root, input, options = {}) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j;
    const result = validateMapDef(input);
    if (!result.ok) {
      throw new Error(`Invalid map definition:
  ${result.errors.join("\n  ")}`);
    }
    const def = result.def;
    const kind = def.kind ?? "geo";
    const messages = { ...defaultMessages, ...options.messages };
    root.classList.add("mapmagic");
    root.replaceChildren();
    const mapElement = document.createElement("div");
    mapElement.className = "mapmagic__map";
    mapElement.style.height = def.height ?? "500px";
    const panels = document.createElement("div");
    panels.className = "mapmagic__panels";
    root.append(mapElement, panels);
    const image = kind === "image" ? def.basemaps[0] : null;
    const scrollMode = ((_a = def.interaction) == null ? void 0 : _a.scrollWheelZoom) ?? "ctrl";
    const map = L$1.map(mapElement, {
      crs: image ? L$1.CRS.Simple : L$1.CRS.EPSG3857,
      zoomControl: ((_b = def.controls) == null ? void 0 : _b.zoom) ?? true,
      dragging: ((_c = def.interaction) == null ? void 0 : _c.dragging) ?? true,
      scrollWheelZoom: scrollMode !== "never",
      minZoom: ((_d = def.view) == null ? void 0 : _d.minZoom) ?? (image ? -5 : void 0),
      maxZoom: ((_e = def.view) == null ? void 0 : _e.maxZoom) ?? (image ? 3 : void 0),
      // Image maps need fractional zoom, otherwise the plan rarely fills the container.
      zoomSnap: image ? 0.25 : 1,
      attributionControl: true
    });
    map.attributionControl.setPrefix(false);
    const cleanups = [];
    if (scrollMode === "ctrl") {
      cleanups.push(installCtrlScroll(map, messages));
    }
    const toLatLng = image ? (p) => L$1.latLng(imageToLatLng([p[0], p[1]], image.height)) : (p) => L$1.latLng(p[1], p[0]);
    const legend = new LegendControl(messages);
    if (((_f = def.controls) == null ? void 0 : _f.legend) ?? true) legend.addTo(map);
    const filters = new SlotControl("mapmagic-filters", "topright");
    filters.addTo(map);
    if (((_g = def.controls) == null ? void 0 : _g.scale) && !image) L$1.control.scale({ imperial: false }).addTo(map);
    if ((_h = def.controls) == null ? void 0 : _h.fullscreen) new FullscreenControl(root, messages).addTo(map);
    const panelSlots = /* @__PURE__ */ new Map();
    const ctx = {
      map,
      kind,
      messages,
      toLatLng,
      setLegend: (id, title, rows) => legend.setSection(id, title, rows),
      clearLegend: (id) => legend.clearSection(id),
      panelSlot: (id) => slot(panelSlots, panels, id, "mapmagic__panel"),
      filterSlot: (id) => filters.slot(id)
    };
    const baseLayers = {};
    let imageBounds = null;
    def.basemaps.forEach((basemap, index) => {
      const layer = createBasemap(basemap);
      baseLayers[basemap.label ?? basemap.id] = layer;
      if (index === 0) layer.addTo(map);
      if (basemap.type === "image") {
        imageBounds = L$1.latLngBounds([0, 0], [basemap.height, basemap.width]);
      }
    });
    if (imageBounds) {
      map.setMaxBounds(imageBounds.pad(0.25));
    }
    const loaded = await Promise.all(
      def.layers.map(async (layerDef) => {
        try {
          const data = await loadSource(layerDef.source);
          return createLayer(layerDef, data, ctx);
        } catch (error) {
          console.error(`[MapMagic] layer "${layerDef.id}" skipped:`, error);
          showLayerError(panels, messages, layerDef);
          return null;
        }
      })
    );
    const layers = /* @__PURE__ */ new Map();
    const overlays = {};
    const byLeaflet = /* @__PURE__ */ new Map();
    def.layers.forEach((layerDef, index) => {
      var _a2, _b2;
      const rendered = loaded[index];
      if (!rendered) return;
      layers.set(rendered.id, rendered);
      byLeaflet.set(rendered.leafletLayer, rendered);
      if (layerDef.listed !== false) overlays[rendered.label] = rendered.leafletLayer;
      if (layerDef.visible !== false) {
        rendered.leafletLayer.addTo(map);
        (_a2 = rendered.onShow) == null ? void 0 : _a2.call(rendered);
      } else {
        (_b2 = rendered.onHide) == null ? void 0 : _b2.call(rendered);
      }
    });
    map.on("overlayadd", (e) => {
      var _a2, _b2;
      return (_b2 = (_a2 = byLeaflet.get(e.layer)) == null ? void 0 : _a2.onShow) == null ? void 0 : _b2.call(_a2);
    });
    map.on("overlayremove", (e) => {
      var _a2, _b2;
      return (_b2 = (_a2 = byLeaflet.get(e.layer)) == null ? void 0 : _a2.onHide) == null ? void 0 : _b2.call(_a2);
    });
    const layersControl = ((_i = def.controls) == null ? void 0 : _i.layers) ?? "auto";
    const showLayersControl = layersControl === true || layersControl === "auto" && (def.basemaps.length > 1 || Object.keys(overlays).length > 0);
    if (showLayersControl) {
      L$1.control.layers(def.basemaps.length > 1 ? baseLayers : {}, overlays, { collapsed: true, position: "topright" }).addTo(map);
    }
    const padding = ((_j = def.view) == null ? void 0 : _j.padding) ?? 20;
    const resetView = () => {
      var _a2;
      map.invalidateSize({ animate: false });
      if ((_a2 = def.view) == null ? void 0 : _a2.center) {
        map.setView(toLatLng(def.view.center), def.view.zoom ?? (image ? 0 : 13), { animate: false });
        return;
      }
      let bounds = null;
      for (const rendered of layers.values()) {
        if (!map.hasLayer(rendered.leafletLayer)) continue;
        const b = rendered.bounds();
        if (b == null ? void 0 : b.isValid()) bounds = bounds ? bounds.extend(b) : L$1.latLngBounds(b.getSouthWest(), b.getNorthEast());
      }
      const target = imageBounds ?? bounds;
      if (target) {
        map.fitBounds(target, { padding: [padding, padding], animate: false });
      } else {
        map.setView(FALLBACK_CENTER, FALLBACK_ZOOM, { animate: false });
      }
    };
    resetView();
    const toPosition = image ? (latLng) => {
      const [x, y] = latLngToImage([latLng.lat, latLng.lng], image.height);
      return [Math.round(x), Math.round(y)];
    } : (latLng) => [round(latLng.lng, GEO_DECIMALS), round(latLng.lat, GEO_DECIMALS)];
    return {
      def,
      map,
      kind,
      layers,
      toLatLng,
      toPosition,
      getView: () => ({ center: toPosition(map.getCenter()), zoom: round(map.getZoom(), 2) }),
      resetView,
      destroy() {
        cleanups.forEach((fn) => fn());
        map.remove();
        root.replaceChildren();
      }
    };
  }
  function round(value, decimals) {
    const factor = 10 ** decimals;
    return Math.round(value * factor) / factor;
  }
  function createBasemap(def) {
    if (def.type === "image") {
      return L$1.imageOverlay(def.url, L$1.latLngBounds([0, 0], [def.height, def.width]), {
        attribution: def.attribution
      });
    }
    return L$1.tileLayer(def.url, {
      attribution: def.attribution,
      maxZoom: def.maxZoom ?? 19,
      subdomains: def.subdomains ?? "abc"
    });
  }
  function createLayer(def, data, ctx) {
    switch (def.type) {
      case "markers":
        return createMarkerLayer(def, data, ctx);
      case "areas":
        return createAreaLayer(def, data, ctx);
      case "route":
        return createRouteLayer(def, data, ctx);
    }
  }
  async function loadSource(source) {
    if ("data" in source) {
      return source.type === "gpx" ? gpxToGeoJson(source.data) : source.data;
    }
    const response = await fetch(source.url, { credentials: "same-origin" });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status} for ${source.url}`);
    }
    if (source.type === "gpx") {
      return gpxToGeoJson(await response.text());
    }
    const json = await response.json();
    if ((json == null ? void 0 : json.type) !== "FeatureCollection" || !Array.isArray(json.features)) {
      throw new Error(`${source.url} is not a GeoJSON FeatureCollection`);
    }
    return json;
  }
  function slot(registry, parent, id, className) {
    let element = registry.get(id);
    if (!element) {
      element = document.createElement("div");
      element.className = className;
      element.dataset.layer = id;
      parent.append(element);
      registry.set(id, element);
    }
    return element;
  }
  function showLayerError(panels, messages, def) {
    const note = document.createElement("p");
    note.className = "mapmagic__error";
    note.setAttribute("role", "status");
    note.innerHTML = renderTemplate(escapeHtml(messages.layerError), { label: def.label ?? def.id });
    panels.append(note);
  }
  const SCHEMA_VERSION = 1;
  function install(target, def, options) {
    return createMap(getHtmlElement(target), def, options).catch((error) => {
      console.error("[MapMagic]", error);
      return null;
    });
  }
  function autoInstall(root = document) {
    const elements = root.querySelectorAll("[data-mapmagic]:not([data-mapmagic-ready])");
    return Promise.all(
      Array.from(elements, (element) => {
        element.dataset.mapmagicReady = "";
        try {
          const def = JSON.parse(element.dataset.mapmagic ?? "");
          const messages = element.dataset.mapmagicMessages ? JSON.parse(element.dataset.mapmagicMessages) : void 0;
          return install(element, def, { messages });
        } catch (error) {
          console.error("[MapMagic] invalid data-mapmagic JSON on", element, error);
          return Promise.resolve(null);
        }
      })
    );
  }
  if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => void autoInstall());
    } else {
      void autoInstall();
    }
  }
  exports2.SCHEMA_VERSION = SCHEMA_VERSION;
  exports2.autoInstall = autoInstall;
  exports2.colorLegend = colorLegend;
  exports2.createMap = createMap;
  exports2.defaultMessages = defaultMessages;
  exports2.distinctValues = distinctValues;
  exports2.escapeHtml = escapeHtml;
  exports2.gpxToGeoJson = gpxToGeoJson;
  exports2.imageToLatLng = imageToLatLng;
  exports2.install = install;
  exports2.latLngToImage = latLngToImage;
  exports2.loadSource = loadSource;
  exports2.pinIcon = pinIcon;
  exports2.renderTemplate = renderTemplate;
  exports2.validateMapDef = validateMapDef;
  Object.defineProperty(exports2, Symbol.toStringTag, { value: "Module" });
});
//# sourceMappingURL=mapmagic.js.map
