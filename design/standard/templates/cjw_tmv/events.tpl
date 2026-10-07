{* The events of a tmv_container: filter, list, pager (v2: Cjw\TmvBundle blocks/app/tmv_events/{list,toolbar,
   recent}.html.twig), from the imported event objects.
   Input: ev_container (the tmv_container node), ev_mode ('list' with the filter, 'toolbar' the filter only,
          'recent' without it), ev_limit (0 = the container's page limit), ev_view_type ('list' or 'grid'),
          ev_columns (grid columns), ev_exclude (category ids, comma separated) *}
{def $ev_filter = fetch( 'cjw_tmv', 'filter_params' )
     $ev_page = 1
     $ev_url = $ev_container.url_alias}
{if ezhttp_hasvariable( 'page', 'get' )}{set $ev_page = max( 1, ezhttp( 'page', 'get' )|int )}{/if}
{if $ev_mode|ne( 'recent' )}
{include uri='design:cjw_tmv/toolbar.tpl'
         tb_filter=$ev_filter
         tb_categories=fetch( 'cjw_tmv', 'categories', hash( 'container', $ev_container ) )
         tb_places=fetch( 'cjw_tmv', 'places', hash( 'container', $ev_container ) )
         tb_action=$ev_url|ezurl}
{/if}
{if $ev_mode|ne( 'toolbar' )}
{def $ev_limit_used = first_set( $ev_limit, 0 )|int}
{def $ev_result = fetch( 'cjw_tmv', 'events', hash( 'container', $ev_container, 'filter', $ev_filter,
                                                   'offset', 0, 'limit', 1, 'exclude_categories', first_set( $ev_exclude, '' ) ) )}
{if $ev_limit_used|lt( 1 )}{set $ev_limit_used = $ev_container.data_map.page_limit.content|int}{/if}
{if $ev_limit_used|lt( 1 )}{set $ev_limit_used = ezini( 'List', 'PageLimit', 'cjw_tmv.ini' )|int}{/if}
{def $ev_pages = $ev_result.total|div( $ev_limit_used )|ceil}
{if $ev_page|gt( max( 1, $ev_pages ) )}{set $ev_page = max( 1, $ev_pages )}{/if}
{set $ev_result = fetch( 'cjw_tmv', 'events', hash( 'container', $ev_container, 'filter', $ev_filter,
                                                   'offset', $ev_page|dec|mul( $ev_limit_used ), 'limit', $ev_limit_used,
                                                   'exclude_categories', first_set( $ev_exclude, '' ) ) )}
{if $ev_result.list|count}
    <div class="{if first_set( $ev_view_type, 'list' )|eq( 'grid' )}grid-row{else}list-row{/if}">
        {foreach $ev_result.list as $ev_event}
            <div class="{if first_set( $ev_view_type, 'list' )|eq( 'grid' )}grid-item cols-{first_set( $ev_columns, 1 )|wash}{else}list-item{/if}">
                {include uri='design:cjw_tmv/line.tpl' ln_item=$ev_event ln_columns='line'}
            </div>
        {/foreach}
    </div>
    {include uri='design:parts/pager.tpl' pg_count=$ev_pages pg_current=$ev_page
             pg_url=concat( $ev_url|ezurl( 'no' ), '?', cond( $ev_filter.query|ne( '' ), concat( $ev_filter.query, '&' ), '' ), 'page=' )}
{else}
    <p class="tmv-events-none">{'No events found!'|i18n( 'extension/cjw_tmv' )}</p>
{/if}
{undef $ev_limit_used $ev_result $ev_pages}
{/if}
{undef $ev_filter $ev_page $ev_url}
