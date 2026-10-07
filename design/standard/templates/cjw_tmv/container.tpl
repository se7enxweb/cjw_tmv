{* Hook for the full view of a tmv_container, included by the design's content/full/tmv_container.tpl:
     cn_part 'event'  : the event of the view parameter "event" (prints nothing when it is not in the feed;
                        cjw_tmv_event_found is set to true/false in the root scope for the caller)
     cn_part 'list'   : the filter and the events (cjw_tmv.ini [ContainerView] ShowEventList)
   The page is not view-cached: the feed changes without the container being published.
   Input: node, cn_part, view_parameters *}
{set-block scope=root variable=cache_ttl}0{/set-block}
{if $cn_part|eq( 'event' )}
    {def $cn_event = fetch( 'cjw_tmv', 'event', hash( 'container', $node, 'id', first_set( $view_parameters.event, 0 )|int ) )}
    {if $cn_event}
        {include uri='design:cjw_tmv/event.tpl' et_event=$cn_event}
    {/if}
    {undef $cn_event}
{elseif and( $cn_part|eq( 'list' ), ezini( 'ContainerView', 'ShowEventList', 'cjw_tmv.ini' )|eq( 'enabled' ) )}
    <div class="tmv-events">
        {include uri='design:cjw_tmv/events.tpl' ev_container=$node ev_mode='list' ev_limit=0 ev_view_type='list' ev_columns=1 ev_exclude=''}
    </div>
{/if}
