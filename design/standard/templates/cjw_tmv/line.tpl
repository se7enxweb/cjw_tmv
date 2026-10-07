{* One event in a list, at its next date (v2: Cjw\TmvBundle content/line/tmv_date.html.twig, view line_details).
   Input: ln_item (an item of fetch( 'cjw_tmv', 'events' ).list: node, next_start, short_text),
          ln_columns ('line' or 'line_h'). *}
{def $ln_node = $ln_item.node
     $ln_col_content = 'col-12 col-sm-6 col-md-6 col-lg-8'
     $ln_col_image = 'col-12 col-sm-6 col-md-6 col-lg-4'
     $ln_image = fetch( 'content', 'list', hash( 'parent_node_id', $ln_node.node_id, 'limit', 1,
                                                 'class_filter_type', 'include', 'class_filter_array', array( 'tmv_image' ),
                                                 'sort_by', array( 'published', true() ) ) )}
{if first_set( $ln_columns, 'line' )|eq( 'line_h' )}
    {set $ln_col_content = 'col-12 col-sm-6 col-md-6 col-lg-6' $ln_col_image = 'col-12 col-sm-6 col-md-6 col-lg-6'}
{/if}
<article class="view-type view-type-line_details tmv-event vl4">
    <header class="article-header">
        <h2 class="title"><a href={$ln_node.url_alias|ezurl}>{$ln_node.data_map.title.content|wash}</a></h2>
    </header>

    <div class="container">
        <div class="row">
            <div class="{$ln_col_content} article-content">
                <div class="short">
                    {$ln_item.short_text|wash}
                </div>

                <div class="contact-details">
                    {if $ln_node.data_map.location.has_content}{attribute_view_gui attribute=$ln_node.data_map.location}{/if}
                </div>
            </div>

            <div class="{$ln_col_image} article-image image-right">
                {if $ln_image|count}
                    {include uri='design:content/item_parts/image.tpl' ip_node=$ln_image[0] ip_alias='cjw_i770_16-9' ip_link=$ln_node.url_alias|ezurl( 'no' ) ip_figure_class='image cjw-image' ip_fields=array( 'image' )}
                {/if}

                <div class="links">
                    <span class="read-more btn-border">
                        {'next date:'|i18n( 'extension/cjw_tmv' )}<br /> {$ln_item.next_start|datetime( 'custom', '%d.%m.%Y, %H:%i' )} {"o'clock"|i18n( 'extension/cjw_tmv' )}
                    </span>

                    <a href={$ln_node.url_alias|ezurl} class="read-more btn-border">
                        {'more'|i18n( 'extension/cjw_tmv' )}
                    </a>
                </div>
            </div>
        </div>
    </div>
</article>
{undef $ln_node $ln_col_content $ln_col_image $ln_image}
