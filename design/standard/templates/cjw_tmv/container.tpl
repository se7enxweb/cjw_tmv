{* Hook for the full view of a tmv_container, included by the design's content/full/tmv_container.tpl inside
   .full-article-content: the filter and the coming events (cjw_tmv.ini [ContainerView] ShowEventList).
   The page is not view-cached: the filter is a query string, which the view cache does not tell apart.
   Input: node *}
{if ezini( 'ContainerView', 'ShowEventList', 'cjw_tmv.ini' )|eq( 'enabled' )}
{set-block scope=root variable=cache_ttl}0{/set-block}
    <div class="tmv-events">
        {include uri='design:cjw_tmv/events.tpl' ev_container=$node ev_mode='list' ev_limit=0 ev_view_type='list' ev_columns=1 ev_exclude=''}
    </div>
{/if}
