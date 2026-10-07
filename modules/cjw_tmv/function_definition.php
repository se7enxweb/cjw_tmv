<?php
/**
 * Template fetch functions of the TMV event feed. All read the imported objects (see cjwTmvFeed).
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */

$FunctionList = array();

$FunctionList['events'] = array(
    'name' => 'events',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'cjwTmvFunctionCollection', 'method' => 'events' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'container', 'type' => 'mixed', 'required' => true ),
        array( 'name' => 'filter', 'type' => 'array', 'required' => false, 'default' => false ),
        array( 'name' => 'offset', 'type' => 'integer', 'required' => false, 'default' => 0 ),
        array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'default' => 0 ),
        array( 'name' => 'exclude_categories', 'type' => 'mixed', 'required' => false, 'default' => '' ) ) );

$FunctionList['categories'] = array(
    'name' => 'categories',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'cjwTmvFunctionCollection', 'method' => 'categories' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'container', 'type' => 'mixed', 'required' => true ) ) );

$FunctionList['places'] = array(
    'name' => 'places',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'cjwTmvFunctionCollection', 'method' => 'places' ),
    'parameter_type' => 'standard',
    'parameters' => array(
        array( 'name' => 'container', 'type' => 'mixed', 'required' => true ) ) );

$FunctionList['filter_params'] = array(
    'name' => 'filter_params',
    'operation_types' => array( 'read' ),
    'call_method' => array( 'class' => 'cjwTmvFunctionCollection', 'method' => 'filterParams' ),
    'parameter_type' => 'standard',
    'parameters' => array() );

?>
