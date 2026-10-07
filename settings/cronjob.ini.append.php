<?php /* #?ini charset="utf-8"?

# The TMV event feed: php runcronjobs.php -s <siteaccess> cjw_tmv (hourly is a good rhythm)
[CronjobSettings]
ExtensionDirectories[]=cjw_tmv

[CronjobPart-cjw_tmv]
Scripts[]=cjw_tmv_refresh.php

*/ ?>
