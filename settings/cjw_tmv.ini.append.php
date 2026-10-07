<?php /* #?ini charset="utf-8"?

# The TMV event feed (Tourismusverband Mecklenburg-Vorpommern event database).
# The account is NOT shipped: put User and Password into settings/override/cjw_tmv.ini.append.php.

[TMV]
# The JSON API of the TMV event database (infomax imxplatform)
Host=https://tmv.imxplatform.de/imxplatformj/api?imxFormat=json
User=
Password=
# Seconds
ConnectTimeout=5
Timeout=20
# The public page of an event at TMV (the event id is appended); empty = no link
LinkToTmvEvent=https://tmv.imxplatform.de/imxplatform3/events?adjusted=true&id=

[Locations]
# Fallback when the container's "locations" field is empty: Location[<TMV location id>]=<place name>
Location[]

[Client]
# Fallback when the container has no "client_id" field: events of this TMV client too (0 = none)
ClientId=0

[Import]
# Drafts at TMV are shown only when their title carries this prefix (which is then removed)
OnlyInternetPrefix=[Nur Website]
# Container field holding extra TMV event ids
ForeignEventIdsAttrIdentifier=tmv_event_ids
# Defaults when the container has no value in limit_events / limit_import_per_cronjob
LimitEvents=1000
LimitPerRun=100
# An event is fetched again when its cached copy is older than this (seconds)
EventMaxAge=21600

[Images]
MaxPerEvent=4
# Wider images are scaled down to this width (pixels)
MaxWidth=1200

[Cache]
# Below the var directory's cache directory
Directory=cjw_tmv
# Seconds
CategoriesTTL=86400
# Cached events no list names any more are removed after this (seconds)
UnusedMaxAge=604800

[List]
# Events per page when the container has no page_limit
PageLimit=20

[ContainerView]
# enabled: the full view of a tmv_container shows the filter and the events below its title, and an event's page
# at <container>/(event)/<id>. disabled: the title only, as the Nexus v2 site draws the container.
ShowEventList=enabled

*/ ?>
