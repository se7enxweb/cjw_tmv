{* The page of one event (v2: Cjw\TmvBundle content/full/tmv_event.html.twig), drawn by the full view of its
   tmv_container at <container>/(event)/<id>. Reads the feed's file cache only.
   Input: et_event (fetch( 'cjw_tmv', 'event', ... ), never false here) *}
    <article class="view-type view-type-full ng-article vf1 tmv-event{if $et_event.cancelled} tmv-event-cancelled{/if}">
                <h1 class="title"><span class="ezstring-field">{$et_event.title|wash}</span></h1>

        {if $et_event.short|ne( '' )}
                    <div class="article-intro">
                        {$et_event.short}
                    </div>
        {elseif $et_event.sub_title|ne( '' )}
                    <div class="article-intro">
                        <p>{$et_event.sub_title|wash}</p>
                    </div>
        {/if}

            <div class="container">
        {if $et_event.images|count}
                        <div class="imagebar">
                            <div class="row">
            {foreach $et_event.images as $et_image}
                                    <div class="col-xs-6 col-sm-4 col-md-4 col-lg-3">
                                        <a href={$et_image.url|ezroot} title="{$et_image.title|wash}" data-fancybox="event">
                                            <img src={$et_image.url|ezroot} alt="{$et_image.title|wash}" loading="lazy" />
                                        </a>
                                        {if $et_image.copyright|ne( '' )}<small class="copyright">&copy; {$et_image.copyright|wash}</small>{/if}
                                    </div>
            {/foreach}
                            </div>
                        </div>
        {/if}

                <div class="full-article-content">
        {if $et_event.body|ne( '' )}
                            <div class="full-article-body">
                                {$et_event.body}
                            </div>
        {/if}

        {if $et_event.dates|count}
                            <div class="dates">
                                <h3>{'Dates'|i18n( 'extension/cjw_tmv' )}</h3>

                                <ul>
            {foreach $et_event.dates as $et_date}
                                        <li{if $et_date.cancelled} class="cancelled"{/if}>
                                            {if $et_date.has_time}{$et_date.start|datetime( 'custom', '%H:%i' )} {"o'clock"|i18n( 'extension/cjw_tmv' )} {'at'|i18n( 'extension/cjw_tmv' )} {/if}{$et_date.start|datetime( 'custom', '%d.%m.%Y' )}
                                            {if $et_date.end|gt( $et_date.start )}
                                                {'to'|i18n( 'extension/cjw_tmv' )}
                                                {$et_date.end|datetime( 'custom', '%H:%i' )} {"o'clock"|i18n( 'extension/cjw_tmv' )}
                                                {if $et_date.end|datetime( 'custom', '%Y%m%d' )|ne( $et_date.start|datetime( 'custom', '%Y%m%d' ) )}{'at'|i18n( 'extension/cjw_tmv' )} {$et_date.end|datetime( 'custom', '%d.%m.%Y' )}{/if}
                                            {/if}
                                            {if $et_date.cancelled}({'cancelled'|i18n( 'extension/cjw_tmv' )}){/if}
                                        </li>
            {/foreach}
                                </ul>
                            </div>
        {/if}

                        <div class="contact">
                            <h3>{'Contact / address'|i18n( 'extension/cjw_tmv' )}</h3>

            {if $et_event.contact|count}
                            <p>{foreach $et_event.contact as $et_line}{$et_line|wash}{delimiter}<br />{/delimiter}{/foreach}</p>
            {/if}
            {if and( $et_event.location|count, $et_event.location|implode( '|' )|ne( $et_event.contact|implode( '|' ) ) )}
                            <p>{foreach $et_event.location as $et_line}{$et_line|wash}{delimiter}<br />{/delimiter}{/foreach}</p>
            {/if}
            {if $et_event.place|ne( '' )}
                            <p class="place">{$et_event.place|wash}</p>
            {/if}
                        </div>

        {if $et_event.link|ne( '' )}
                        <p class="tmv-link"><a href="{$et_event.link|wash}" target="_blank" rel="noopener">{'This event at the TMV event database'|i18n( 'extension/cjw_tmv' )}</a></p>
        {/if}
                </div>
            </div>
            <div style="clear: both"></div>
    </article>
