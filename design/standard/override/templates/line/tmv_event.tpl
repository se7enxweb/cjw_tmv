{* Line view of tmv_event (v2: Cjw\TmvBundle content/line/tmv_event.html.twig): next date, title, intro, the first
   image on the right. Input: node. *}
{def $tl_date = fetch( 'content', 'list', hash( 'parent_node_id', $node.node_id, 'limit', 1,
                                                'class_filter_type', 'include', 'class_filter_array', array( 'tmv_date' ),
                                                'sort_by', array( 'attribute', true(), 'tmv_date/start' ) ) )
     $tl_image = fetch( 'content', 'list', hash( 'parent_node_id', $node.node_id, 'limit', 1,
                                                 'class_filter_type', 'include', 'class_filter_array', array( 'tmv_image' ),
                                                 'sort_by', array( 'published', true() ) ) )}
<article class="view-type view-type-line tmv-event vl4">
    <div class="container">
        <div class="row">
            <div class="col-12 col-sm-12 col-md-6 col-lg-8 article-content">
                <header class="article-header">
                    <h2 class="title">
                        {if $tl_date|count}
                            <small>
                                {$tl_date[0].data_map.start.data_int|datetime( 'custom', '%d.%m.%Y' )}
                            </small>
                            <br />
                        {/if}
                        <a href={$node.url_alias|ezurl}>{$node.data_map.title.content|wash}</a>
                    </h2>
                </header>

                <div class="short">
                    {if $node.data_map.full_intro.has_content}{attribute_view_gui attribute=$node.data_map.full_intro}{/if}
                </div>
            </div>

            {if $tl_image|count}
                <div class="col-12 col-sm-12 col-md-6 col-lg-4 article-image image-right">
                    {include uri='design:content/item_parts/image.tpl' ip_node=$tl_image[0] ip_alias='cjw_i770' ip_link=$node.url_alias|ezurl( 'no' ) ip_fields=array( 'image' )}
                </div>
            {/if}
        </div>
    </div>
</article>

<hr />
{undef $tl_date $tl_image}
