<?php /* #?ini charset="utf-8"?

# The event views of the starter siteaccesses (site.ini [SiteAccessSettings] ExtensionSettingsSiteAccess=cjw_starter).
[full_cjw_tmv_tmv_event]
Source=node/view/full.tpl
MatchFile=full/tmv_event.tpl
Subdir=templates
Match[class_identifier]=tmv_event

# A date or an image has no page of its own: its event's page instead.
[full_cjw_tmv_tmv_date]
Source=node/view/full.tpl
MatchFile=full/tmv_redirect_to_event.tpl
Subdir=templates
Match[class_identifier]=tmv_date

[full_cjw_tmv_tmv_image]
Source=node/view/full.tpl
MatchFile=full/tmv_redirect_to_event.tpl
Subdir=templates
Match[class_identifier]=tmv_image

[line_cjw_tmv_tmv_event]
Source=node/view/line.tpl
MatchFile=line/tmv_event.tpl
Subdir=templates
Match[class_identifier]=tmv_event

*/ ?>
