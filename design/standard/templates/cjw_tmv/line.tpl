{* One event in a list, at its next date (v2: Cjw\TmvBundle content/line/tmv_date.html.twig, view line_details).
   Input: ln_event (an item of fetch( 'cjw_tmv', 'events' ).list), ln_url (address of the event page, through ezurl,
          not washed), ln_columns ('line' or 'line_h'). *}
{def $ln_col_content = 'col-12 col-sm-6 col-md-6 col-lg-8'
     $ln_col_image = 'col-12 col-sm-6 col-md-6 col-lg-4'}
{if first_set( $ln_columns, 'line' )|eq( 'line_h' )}
    {set $ln_col_content = 'col-12 col-sm-6 col-md-6 col-lg-6' $ln_col_image = 'col-12 col-sm-6 col-md-6 col-lg-6'}
{/if}
<article class="view-type view-type-line_details tmv-event vl4{if $ln_event.cancelled} tmv-event-cancelled{/if}">
    <header class="article-header">
        <h2 class="title"><a href={$ln_url}>{$ln_event.title|wash}</a></h2>
    </header>

    <div class="container">
        <div class="row">
            <div class="{$ln_col_content} article-content">
                <div class="short">
                    {$ln_event.short_text|wash}
                </div>

                <div class="contact-details">
                    {if $ln_event.location|count}<p>{foreach $ln_event.location as $ln_line}{$ln_line|wash}{delimiter}<br />{/delimiter}{/foreach}</p>{/if}
                </div>
            </div>

            <div class="{$ln_col_image} article-image image-right">
                {if $ln_event.image|ne( '' )}
                    <figure class="image cjw-image"><a href={$ln_url}><img src={$ln_event.image|ezroot} alt="{$ln_event.title|wash}" loading="lazy" /></a></figure>
                {/if}

                <div class="links">
                    <span class="read-more btn-border">
                        {if $ln_event.cancelled}{'cancelled'|i18n( 'extension/cjw_tmv' )}{else}{'next date:'|i18n( 'extension/cjw_tmv' )}<br /> {$ln_event.next_start|datetime( 'custom', '%d.%m.%Y, %H:%i' )} {"o'clock"|i18n( 'extension/cjw_tmv' )}{/if}
                    </span>

                    <a href={$ln_url} class="read-more btn-border">
                        {'more'|i18n( 'extension/cjw_tmv' )}
                    </a>
                </div>
            </div>
        </div>
    </div>
</article>
{undef $ln_col_content $ln_col_image}
