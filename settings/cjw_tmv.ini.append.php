<?php /* #?ini charset="utf-8"?

# The TMV event feed (Tourismusverband Mecklenburg-Vorpommern event database), imported as content objects.
# The account is NOT shipped: put User and Password into settings/override/cjw_tmv.ini.append.php.

[TMV]
# The JSON API of the TMV event database (infomax imxplatform)
Host=https://tmv.imxplatform.de/imxplatformj/api?imxFormat=json
User=
Password=
# Seconds
ConnectTimeout=5
Timeout=20

[Locations]
# Fallback when the container's "locations" field is empty: Location[<TMV location id>]=<place name>
Location[]

[Client]
# Fallback when the container has no "client_id" field: events of this TMV client too (0 = none)
ClientId=0

[Import]
# The user the objects are created by (and who removes the past ones)
CreatorUserID=14
# Language of the English translation made when the TMV has an English title (empty = German only)
TranslationLanguage=eng-US
# Drafts at TMV are imported only when their title carries this prefix (which is then removed)
OnlyInternetPrefix=[Nur Website]
# Container field holding extra TMV event ids
ForeignEventIdsAttrIdentifier=tmv_event_ids
# Defaults when the container has no value in limit_events / limit_import_per_cronjob
LimitEvents=1000
LimitPerRun=100
# Coming dates per event at most (0 = all)
MaxDatesPerEvent=0

[Images]
MaxPerEvent=4
# Wider images are scaled down to this width (pixels) before they are stored
MaxWidth=1200

[List]
# Events per page when the container has no page_limit
PageLimit=20

[ContainerView]
# enabled: the full view of a tmv_container shows the filter and the events below its title (owner decision Q28).
# disabled: the title only, as the Nexus v2 site draws the container.
ShowEventList=enabled

*/ ?>
