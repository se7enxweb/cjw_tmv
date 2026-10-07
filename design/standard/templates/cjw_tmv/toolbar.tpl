{* The filter of the event list (v2: Cjw\TmvBundle parts/toolbar.html.twig). The form is sent with GET so the
   pager keeps the filter (v2 posted it; the fields and their names are v2's).
   Input: tb_filter (fetch( 'cjw_tmv', 'filter_params' )), tb_categories (id => name), tb_places (names),
          tb_action (address of the list, through ezurl). *}
<fieldset>
    <legend>{'Filter events'|i18n( 'extension/cjw_tmv' )}</legend>

    <form id="list-events" method="get" action={$tb_action}>
        <div class="form-row list-dates">
            <div class="form-group col-6">
                <label for="start">{'From'|i18n( 'extension/cjw_tmv' )}</label>
                <input class="form-control" type="date" name="Start" id="start" value="{$tb_filter.start|wash}" placeholder="dd.mm.yyyy" />
            </div>

            <div class="form-group col-6">
                <label for="end">{'To'|i18n( 'extension/cjw_tmv' )}</label>
                <input class="form-control" type="date" name="End" id="end" value="{$tb_filter.end|wash}" placeholder="dd.mm.yyyy" />
            </div>
        </div>

        {if $tb_categories|count}
            <div class="form-row list-categories">
                <div class="form-group col-12">
                    <label for="start">{'Category'|i18n( 'extension/cjw_tmv' )}</label>

                    <div class="form-group">
                        {foreach $tb_categories as $tb_id => $tb_name}
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="checkbox_{$tb_id|wash}" name="Categories[]" value="{$tb_id|wash}"{if $tb_filter.categories|contains( $tb_id|wash )} checked="checked"{/if} />
                                <label class="form-check-label" for="checkbox_{$tb_id|wash}">{$tb_name|wash}</label>
                            </div>
                        {/foreach}
                    </div>
                </div>
            </div>
        {/if}

        {if $tb_places|count}
            <div class="form-row list-places">
                <div class="form-group col-12">
                    <label for="start">{'Place'|i18n( 'extension/cjw_tmv' )}</label>

                    <div class="form-group">
                        {foreach $tb_places as $tb_index => $tb_place}
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="checkbox_place_{$tb_index|inc}" name="Places[]" value="{$tb_place|wash}"{if $tb_filter.places|contains( $tb_place )} checked="checked"{/if} />
                                <label class="form-check-label" for="checkbox_place_{$tb_index|inc}">{$tb_place|wash}</label>
                            </div>
                        {/foreach}
                    </div>
                </div>
            </div>
        {/if}

        <div class="form-row searchbar">
            <div class="form-group col-6">
                <input class="form-control" type="text" name="Keyword" id="keyword" value="{$tb_filter.keyword|wash}" placeholder="{'Keyword'|i18n( 'extension/cjw_tmv' )}" />
            </div>

            <div class="form-group col-6">
                <button class="btn btn-primary btn-lg btn-block" type="submit">{'Search'|i18n( 'extension/cjw_tmv' )}</button>
            </div>
        </div>
    </form>
</fieldset>
