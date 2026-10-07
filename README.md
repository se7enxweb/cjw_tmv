# CJW TMV event feed (cjw_tmv)

The events of the TMV event database (Tourismusverband Mecklenburg-Vorpommern, infomax imxplatform JSON API)
for Exponential 6, imported as content objects. A port of the legacy extension `cjw_tmv_veranstdb` (the import)
and of the Nexus v2 `TmvBundle` (classes, views, the Layouts block `tmv_events`).

Status: 0.1.0, the first release.

## What it does

- Cronjob part `cjw_tmv` (`cronjobs/cjw_tmv_refresh.php`, class `cjwTmvImporter`), for every `tmv_container`:
  - the TMV event ids of its locations (field `locations`, lines `id|name`), its client and extra ids;
  - creates the events not there yet, updates those the TMV reports modified since the last run, at most
    `limit_import_per_cronjob` per run and `limit_events` in all;
  - objects: `tmv_event` (remote id `cjw-tmv-<event id>`), its coming dates as `tmv_date`, up to four images as
    `tmv_image` (downloaded, scaled to 1200 px), the categories as `tmv_categorie` below
    `tmv_container_categories`; German, always available, with an English translation when the TMV has one;
  - removes, as the legacy cronjobs did, dates that have ended, events without a coming date and events the TMV no
    longer lists; only below the container and only objects whose remote id starts with `cjw-tmv-`.
- Views (starter siteaccesses, `settings/siteaccess/cjw_starter/override.ini.append.php`): full and line view of
  `tmv_event` in v2's markup; `tmv_date` and `tmv_image` redirect to their event.
- `design:cjw_tmv/container.tpl`: the filter, the events at their next date and the pager, for the full view of a
  `tmv_container` (`[ContainerView] ShowEventList`).
- Layouts block `tmv_events` (view types `list`, `toolbar`, `recent`).
- Fetch functions: `fetch( 'cjw_tmv', 'events' | 'categories' | 'places' | 'filter_params', ... )`.

The templates use the design `starter` of `cjw_themes_jumper` (`content/item_parts/image.tpl`, `parts/pager.tpl`,
the `cjw_i*` image aliases).

## Layout

```
classes/                    cjwTmvClient (API), cjwTmvImporter (objects), cjwTmvFeed (reading, filter),
                            cjwTmvHtml (text cleaning), cjwTmvFunctionCollection, cjwTmvEventsBlockHandler
cronjobs/cjw_tmv_refresh.php
modules/cjw_tmv/            fetch functions only, no views
design/standard/            templates/cjw_tmv/*.tpl, templates/explayouts/block/tmv_events.tpl, override/templates
settings/                   cjw_tmv.ini (no account), cronjob.ini, design.ini, module.ini
settings/siteaccess/cjw_starter/   overrides, the Layouts block, the translations
translations/ger-DE/
```

The classes come with the `cjw_multisite_democontent` package (Nexus v2 TmvBundle package `tmv_classes-1.0-1`).

## Requirements

- Exponential 6, PHP 8.0 or later with `curl` and `json`.
- `cjw_themes_jumper` (the design `starter`) and `explayouts` for the views and the Layouts block.
- A TMV account, kept only in `settings/override/cjw_tmv.ini.append.php`.

See [INSTALL.md](INSTALL.md).

## License

GNU General Public License v2.0 (or any later version), see [LICENSE](LICENSE).
