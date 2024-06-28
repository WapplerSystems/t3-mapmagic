# t3-mapmagic

- [Github Projekt `mapmagic`](https://github.com/orgs/WapplerSystems/projects/5/views/1?pane=info)
- [Github Repository `t3-mapmagic`](https://github.com/WapplerSystems/t3-mapmagic)

## Beispiel YAML Konfiguration

```yaml
# Ziele:
#   - Die YAML kann von einem Editor im Backend generiert werden
#   - Die YAML kann zur Generierung des Frontends eingesetzt werden (als Datenbasis/Source of Truth)

# Konfiguration für Datenquellen, "Woher bekomme ich meine Daten?"
# Beschreibt zentral, wo die Abhängigkeiten von dieser YAML Datei liegen
datasources:
  osm-raster:
    type: xyz
    url: http://a.tile.openstreetmap.org/{z}/{x}/{y}.png

  standorte-geojson:
    type: geojson
    url: "https://.../standorte.geojson"
  konkurrenten-geojson:
    type: geojson
    url: "https://.../konkurrenten.geojson"

# Konfiguration für Kartenebenen, "Welche Daten möchte ich anzeigen?"
# Beschreibt die Reihenfolge der Ebenen, die nacheinander auf die Karte gezeichnet werden
layers:
  basemaps:
    osm:
      label: OpenStreetMap
      datasource: osm-raster
  featuremaps:
    standorte:
      type: markers
      datasource: standorte-geojson
      label: Meine Standorte
    konkurrenten:
      type: markers
      datasource: konkurrenten-geojson
      label: Standorte von Konkurrenten

# Konfiguration für Kartenstile, "Wie möchte ich meine Daten darstellen?"
# Defaults können überschrieben werden, müssen aber nicht (?)
styles:
  featuremaps:
    standorte:
      marker_size: 10px
      marker_color: "#ff0000"
```
