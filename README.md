# MapMagic (TYPO3-Extension `map_magic`)

Interaktive Karten für TYPO3 14: Standortkarten, Gebietskarten, Routen mit
Höhenprofil und Lagepläne auf eigenem Bild. Karten werden im Backend-Modul
**Inhalt → Karten** angelegt und mit dem Inhaltselement „Karte“ auf Seiten
eingebunden.

## Einrichtung

1. `composer require wapplersystems/mapmagic`
2. Site Set **MapMagic** (`wapplersystems/mapmagic`) in der Site-Konfiguration
   ergänzen.
3. Backend-Benutzer brauchen Zugriff auf das Modul „Karten“ und Schreibrecht
   auf den Ordner der Kartendefinitionen (Standard `fileadmin/map_definitions/`,
   konfigurierbar in `Configuration/Yaml/MapSetup.yaml`).

Für Adressen als Datenquelle zusätzlich EXT:address (`wapplersystems/address`).

## Kartendefinitionen

Jede Karte ist eine Datei `*.map.yaml`. Das Format ist das des Renderers
([`Resources/Public/Lib/MapMagicJs/src/schema.ts`](Resources/Public/Lib/MapMagicJs/src/schema.ts)),
dazu kommen ein Dateikopf (`type: MapMagic`, `identifier`, `label`) und
TYPO3-Erleichterungen, die der Server auflöst:

- Pfade als `EXT:…`, `fileadmin/…`, `1:/…` oder `t3://file?uid=…`
- Basiskarten als `preset: osm | osm-de | carto-light`
- Quelle `type: addresses` mit `pids` (und optional `categories`) für EXT:address

Beispiele für alle Kartentypen: [`Resources/Private/Maps/`](Resources/Private/Maps/)
(schreibgeschützte Demokarten, im Modul sichtbar).

## Kachelserver

`tile.openstreetmap.org` ist ein spendenfinanzierter Dienst mit
Nutzungsbedingungen, die starke Nutzung untersagen. Für Websites mit
nennenswertem Verkehr den Preset auf einen eigenen Kachel-Proxy umstellen:

```php
// config/system/additional.php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['map_magic']['basemapPresets']['osm']['url']
    = 'https://tiles.example.org/{z}/{x}/{y}.png';
```

Die Hosts aller Presets werden automatisch für die Content-Security-Policy
(`img-src`) freigegeben. Von Hand in einer Karte eingetragene Kachelserver
muss die Website selbst in ihrer `csp.yaml` erlauben.

## Entwicklung

Der Renderer ist ein eigenes Repository
([WapplerSystems/magicmap](https://github.com/WapplerSystems/magicmap)), als
Submodule eingebunden. Nach Änderungen am Renderer:

```bash
git submodule update --init
Build/build-renderer.sh   # prüft, baut und kopiert nach Resources/Public/Frontend/
```

Die kopierten Dateien zusammen mit dem Submodule-Zeiger committen.

Das Backend-Modul besteht aus Lit-Komponenten ohne Build-Schritt
(`Resources/Public/JavaScript/backend/`). Im Production-Kontext hängt der
Cache-Buster der Import-Map an der Extension-Version: nach JavaScript-
Änderungen die Version in `ext_emconf.php` erhöhen, sonst behalten Browser
die alten Module.
