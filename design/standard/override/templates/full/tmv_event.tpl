{* Full view of tmv_event, an imported TMV event (v2: Cjw\TmvBundle content/full/tmv_event.html.twig): title,
   intro, the images (tmv_image children, fancybox gallery), the body, the coming dates (tmv_date children by
   start), contact / address and place. Input: node. *}
{def $te_dm = $node.data_map
     $te_dates = fetch( 'content', 'list', hash( 'parent_node_id', $node.node_id,
                                                 'class_filter_type', 'include', 'class_filter_array', array( 'tmv_date' ),
                                                 'sort_by', array( 'attribute', true(), 'tmv_date/start' ) ) )
     $te_images = fetch( 'content', 'list', hash( 'parent_node_id', $node.node_id,
                                                  'class_filter_type', 'include', 'class_filter_array', array( 'tmv_image' ),
                                                  'sort_by', array( 'published', true() ) ) )
     $te_now = currentdate()}
    <article class="view-type view-type-full ng-article vf1">
                <h1 class="title"><span class="ezstring-field">{$te_dm.title.content|wash}</span></h1>

        {if $te_dm.full_intro.has_content}
                    <div class="article-intro">
                        {attribute_view_gui attribute=$te_dm.full_intro}
                    </div>
        {/if}

            <div class="container">
        {if $te_images|count}
                        <div class="imagebar">
                            <div class="row">
            {foreach $te_images as $te_image}
                                    <div class="col-xs-6 col-sm-4 col-md-4 col-lg-3">
                                        <a href="{$te_image.data_map.image.content['cjw_i1200'].url|ezroot( 'no' )}" title="{$te_image.name|wash}" data-fancybox="event">
                                            {include uri='design:content/item_parts/image.tpl' ip_node=$te_image ip_alias='cjw_i770' ip_fields=array( 'image' )}
                                        </a>
                                    </div>
            {/foreach}
                            </div>
                        </div>
        {/if}

                <div class="full-article-content">
        {if $te_dm.body.has_content}
                            <div class="full-article-body">
                                {attribute_view_gui attribute=$te_dm.body}
                            </div>
        {/if}

        {if $te_dates|count}
                            <div class="dates">
                                <h3>{'Dates'|i18n( 'extension/cjw_tmv' )}</h3>

                                <ul>
            {foreach $te_dates as $te_date}
                {def $te_start = $te_date.data_map.start.data_int
                     $te_end = $te_date.data_map.end.data_int}
                {if max( $te_start, $te_end )|ge( $te_now )}
                                        <li>
                                            {$te_start|datetime( 'custom', '%H:%i' )}

                                            {"o'clock"|i18n( 'extension/cjw_tmv' )}

                                            {'at'|i18n( 'extension/cjw_tmv' )}

                                            {$te_start|datetime( 'custom', '%d.%m.%Y' )}

                    {if $te_end|gt( $te_start )}
                                                {'to'|i18n( 'extension/cjw_tmv' )}

                                                {$te_end|datetime( 'custom', '%H:%i' )}

                                                {"o'clock"|i18n( 'extension/cjw_tmv' )}

                                                {'at'|i18n( 'extension/cjw_tmv' )}

                                                {$te_end|datetime( 'custom', '%d.%m.%Y' )}
                    {/if}
                                        </li>
                {/if}
                {undef $te_start $te_end}
            {/foreach}
                                </ul>
                            </div>
        {/if}

                        <div class="contact">
                            <h3>{'Contact / address'|i18n( 'extension/cjw_tmv' )}</h3>

            {if $te_dm.contact.has_content}
                            {attribute_view_gui attribute=$te_dm.contact}
            {/if}

            {if and( $te_dm.location.has_content, $te_dm.location.data_text|ne( $te_dm.contact.data_text ) )}
                            {attribute_view_gui attribute=$te_dm.location}
            {/if}

            {if $te_dm.place.has_content}
                            <span class="ezstring-field">{$te_dm.place.content|wash}</span>
            {/if}
                        </div>
                </div>
            </div>
            <div style="clear: both"></div>
    </article>
{undef $te_dm $te_dates $te_images $te_now}
