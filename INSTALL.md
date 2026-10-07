# Installing CJW TMV event feed

1. Put the extension in `extension/cjw_tmv`.
2. Activate it in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=cjw_tmv
   ```

   The Layouts block and the translations reach the siteaccesses with
   `[SiteAccessSettings] ExtensionSettingsSiteAccess=cjw_starter`.
3. Put the TMV account into `settings/override/cjw_tmv.ini.append.php` (never commit it):

   ```ini
   [TMV]
   User=<account>
   Password=<password>
   ```

4. Create a `tmv_container` object (fields `locations`: lines `<TMV location id>|<place>`, `limit_events`,
   `limit_import_per_cronjob`, `page_limit`). Its full view includes `design:cjw_tmv/container.tpl`.
5. Regenerate the autoloads and clear the caches:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-tag=ini --allow-root-user
   php bin/php/ezcache.php --clear-tag=template --allow-root-user
   ```

6. Fill the cache, then run it hourly from cron:

   ```bash
   php runcronjobs.php -s <siteaccess> cjw_tmv
   ```

   Run it as the web server's user, or make sure the files below `var/<site>/cache/cjw_tmv` and
   `var/<site>/cache/public/cjw_tmv` stay readable by it.
