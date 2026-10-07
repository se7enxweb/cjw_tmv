{* The Layouts block tmv_events (v2: Cjw\TmvBundle blocks/app/tmv_events/<view type>.html.twig): the events of
   the block's event container. View types: list (filter + events), toolbar (filter only), recent (events only).
   Values from cjwTmvEventsBlockHandler. *}
{def $tb_values = first_set( $block.values, hash() )
     $tb_container = cond( first_set( $tb_values.parent_node_id, 0 )|gt( 0 ), fetch( 'content', 'node', hash( 'node_id', $tb_values.parent_node_id ) ), false() )}
{if and( $tb_container, $tb_container.class_identifier|eq( 'tmv_container' ) )}
{set-block scope=root variable=cache_ttl}0{/set-block}
{include uri='design:cjw_tmv/events.tpl' ev_container=$tb_container
         ev_mode=cond( array( 'toolbar', 'recent' )|contains( $block.view_type ), $block.view_type, 'list' )
         ev_limit=$tb_values.limit ev_view_type=$tb_values.list_view_type ev_columns=$tb_values.number_of_columns
         ev_exclude=$tb_values.exclude_categories}
{/if}
{undef $tb_values $tb_container}
