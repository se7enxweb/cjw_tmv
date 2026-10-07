# CJW TMV event feed (cjw_tmv)

The events of the TMV event database (Tourismusverband Mecklenburg-Vorpommern, infomax imxplatform JSON API)
for Exponential 6. A port of the legacy extension `cjw_tmv_veranstdb` and of the Nexus v2 `TmvBundle`, with one
change of approach: the events are **not** imported as content objects. A cronjob part fetches them into a file
cache, and the pages read only that cache.

Status: 0.1.0, first port. Not yet released.

## What it does

- `cronjobs/cjw_tmv_refresh.php` (cronjob part `cjw_tmv`): for every `tmv_container` node, the TMV event ids of
  its locations (field `locations`, lines `id|name`) and client, then the events (at most
  `limit_import_per_cronjob` per run, missing ones first), their images (scaled, stored below
  `var/<site>/cache/public/cjw_tmv/images`) and the TMV categories. A failed API call keeps the previous files.
- `cjw_tmv/container.tpl`: hook for the full view of a `tmv_container` (filter, list, pager; and the event page
  at `<container>/(event)/<id>`). `[ContainerView] ShowEventList=disabled` keeps the title-only view of v2.
- Layouts block `tmv_events` (view types `list`, `toolbar`, `recent`) for the siteaccesses that load
  `settings/siteaccess/cjw_starter`.
- Fetch functions: `fetch( 'cjw_tmv', 'events' | 'event' | 'categories' | 'places' | 'filter_params', ... )`.

## Layout

```
classes/                    cjwTmvClient (API), cjwTmvFeed (cache, refresh, filter), cjwTmvHtml (text cleaning),
                            cjwTmvFunctionCollection (fetch functions), cjwTmvEventsBlockHandler (Layouts block)
cronjobs/cjw_tmv_refresh.php
modules/cjw_tmv/            fetch functions only, no views
design/standard/templates/  cjw_tmv/*.tpl, explayouts/block/tmv_events.tpl
settings/                   cjw_tmv.ini (no account), cronjob.ini, design.ini, module.ini
settings/siteaccess/cjw_starter/   the Layouts block and the translations, for the starter siteaccesses
translations/ger-DE/
```

See [INSTALL.md](INSTALL.md).

## License

GNU General Public License v2.0 (or any later version), see [LICENSE](LICENSE).
