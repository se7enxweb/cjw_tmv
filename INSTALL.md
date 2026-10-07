# Installing CJW TMV event feed

1. Put the extension in `extension/cjw_tmv` and activate it in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=cjw_tmv
   ```

   The views, the Layouts block and the translations reach the siteaccesses with
   `[SiteAccessSettings] ExtensionSettingsSiteAccess=cjw_starter`.
2. Install the classes `tmv_container`, `tmv_container_categories`, `tmv_event`, `tmv_date`, `tmv_image` and
   `tmv_categorie` (the `cjw_multisite_democontent` package carries them).
3. Put the TMV account into `settings/override/cjw_tmv.ini.append.php` (never commit it):

   ```ini
   [TMV]
   User=<account>
   Password=<password>
   ```

4. Create a `tmv_container` object (fields `locations`: lines `<TMV location id>|<place>`, `limit_events`,
   `limit_import_per_cronjob`, `page_limit`) with a `tmv_container_categories` object below it.
5. Regenerate the autoloads and clear the caches:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   php bin/php/ezcache.php --clear-tag=ini --allow-root-user
   php bin/php/ezcache.php --clear-tag=template --allow-root-user
   ```

6. Import, as the web server's user and with the German siteaccess; a first run can be bounded:

   ```bash
   CJW_TMV_LIMIT=20 php runcronjobs.php -s <German siteaccess> cjw_tmv
   ```

   `CJW_TMV_DRY_RUN=1` asks the TMV and writes nothing.

7. The extension installs no cron entry: run the cronjob part `cjw_tmv` by hand as above whenever the events should
   be refreshed, or add a cron entry yourself, e.g. hourly:
   `15 * * * * cd <root> && php runcronjobs.php -q -s <German siteaccess> cjw_tmv`.
