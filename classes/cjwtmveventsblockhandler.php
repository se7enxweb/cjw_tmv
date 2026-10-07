<?php
/**
 * The Layouts block "tmv_events" (v2: Cjw\TmvBundle TmvEventsHandler): the events of a tmv_container, with the
 * filter toolbar (view type list), only the toolbar (toolbar) or without it (recent).
 * Template: design:explayouts/block/tmv_events.tpl.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvEventsBlockHandler implements expLayoutsBlockHandlerInterface
{
    public function getParameters()
    {
        return array(
            'parent' => array( 'name' => 'Event container', 'type' => 'item_link', 'default' => '' ),
            'limit' => array( 'name' => 'Events per page', 'type' => 'integer', 'default' => 10 ),
            'view_type' => array( 'name' => 'View type', 'type' => 'choice', 'options' => array( 'list' => 'List', 'grid' => 'Grid' ), 'default' => 'list' ),
            'number_of_columns' => array( 'name' => 'Columns (grid)', 'type' => 'integer', 'default' => 1 ),
            'exclude_categories' => array( 'name' => 'Categories left out of "all", comma separated', 'type' => 'text', 'default' => '' ),
        );
    }

    public function getValues( $block )
    {
        $params = is_array( $block ) && isset( $block['parameters'] ) ? $block['parameters'] : array();
        $parent = isset( $params['parent'] ) ? $params['parent'] : '';
        if ( is_array( $parent ) )
            $parent = isset( $parent['value'] ) ? $parent['value'] : ( isset( $parent['node_id'] ) ? $parent['node_id'] : '' );
        $nodeId = preg_match( '#(\d+)\s*$#', (string)$parent, $m ) ? (int)$m[1] : 0;
        $columns = isset( $params['number_of_columns'] ) ? (int)$params['number_of_columns'] : 1;
        return array(
            'parent_node_id' => $nodeId,
            'limit' => isset( $params['limit'] ) && (int)$params['limit'] > 0 ? (int)$params['limit'] : 10,
            'list_view_type' => isset( $params['view_type'] ) && $params['view_type'] === 'grid' ? 'grid' : 'list',
            'number_of_columns' => $columns > 0 ? $columns : 1,
            'exclude_categories' => isset( $params['exclude_categories'] ) ? (string)$params['exclude_categories'] : '',
        );
    }
}

?>
