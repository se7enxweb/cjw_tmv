<?php
/**
 * The fetch functions of the cjw_tmv module (modules/cjw_tmv/function_definition.php).
 * A container that is not a tmv_container answers with an empty result, never an error page.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvFunctionCollection
{
    /**
     * fetch( 'cjw_tmv', 'events', hash( 'container', $node, 'filter', $filter, 'offset', 0, 'limit', 20,
     *                                   'exclude_categories', '12,13' ) )
     * @return array result: list, total, built, limit
     */
    public static function events( $container, $filter = false, $offset = 0, $limit = 0, $excludeCategories = '' )
    {
        $feed = cjwTmvFeed::forContainer( $container );
        if ( !$feed )
            return array( 'result' => array( 'list' => array(), 'total' => 0, 'built' => 0, 'limit' => (int)$limit ) );
        if ( !is_array( $filter ) )
            $filter = cjwTmvFeed::filterParams();
        $exclude = is_array( $excludeCategories ) ? $excludeCategories : explode( ',', (string)$excludeCategories );
        return array( 'result' => $feed->events( $filter, (int)$offset, (int)$limit, $exclude ) );
    }

    /**
     * fetch( 'cjw_tmv', 'event', hash( 'container', $node, 'id', $id ) )
     * @return array result: the event, or false
     */
    public static function event( $container, $id )
    {
        $feed = cjwTmvFeed::forContainer( $container );
        return array( 'result' => $feed ? $feed->event( (int)$id ) : false );
    }

    /**
     * fetch( 'cjw_tmv', 'categories', hash( 'container', $node ) )
     * @return array result: id => name
     */
    public static function categories( $container )
    {
        $feed = cjwTmvFeed::forContainer( $container );
        return array( 'result' => $feed ? $feed->categories() : array() );
    }

    /**
     * fetch( 'cjw_tmv', 'places', hash( 'container', $node ) )
     * @return array result: place names
     */
    public static function places( $container )
    {
        $feed = cjwTmvFeed::forContainer( $container );
        return array( 'result' => $feed ? $feed->places() : array() );
    }

    /**
     * fetch( 'cjw_tmv', 'filter_params' )
     * @return array result: the filter of the current request
     */
    public static function filterParams()
    {
        return array( 'result' => cjwTmvFeed::filterParams() );
    }
}

?>
