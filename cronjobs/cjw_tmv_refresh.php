<?php
/**
 * Cronjob part cjw_tmv: refreshes the TMV event feed of every tmv_container (the file cache the pages read).
 *
 *   php runcronjobs.php -s <siteaccess> cjw_tmv
 *
 * Calls the TMV API only; writes no content. At most "limit_import_per_cronjob" (container field, default 100)
 * events are downloaded per container and run, missing ones first, so a first run on a large feed fills the cache
 * over several runs. When the API cannot be reached, the previous lists stay in place.
 * Prints counts only, never the account or anyone's contact data.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */

if ( !isset( $cli ) || !$cli instanceof eZCLI )
    $cli = eZCLI::instance();

$client = new cjwTmvClient();
if ( !$client->isConfigured() )
{
    $cli->warning( 'cjw_tmv: no TMV account in cjw_tmv.ini [TMV] User/Password (settings/override), nothing refreshed' );
    return;
}

$lockDir = cjwTmvFeed::cacheDir();
if ( !is_dir( $lockDir ) )
    @mkdir( $lockDir, 0775, true );
$lock = @fopen( $lockDir . '/refresh.lock', 'c' );
if ( !$lock || !flock( $lock, LOCK_EX | LOCK_NB ) )
{
    $cli->warning( 'cjw_tmv: another refresh is running, skipped' );
    return;
}

$force = in_array( '--tmv-force', isset( $_SERVER['argv'] ) ? (array)$_SERVER['argv'] : array() );
$cli->output( 'cjw_tmv: ' . cjwTmvFeed::refreshCategories( $client, $force ) );

$usedIds = array();
foreach ( cjwTmvFeed::fetchContainers() as $node )
{
    $feed = new cjwTmvFeed( $node );
    $started = microtime( true );
    $feed->refresh( $client, $force );
    foreach ( $feed->log as $line )
        $cli->output( 'cjw_tmv: container ' . $node->attribute( 'node_id' ) . ': ' . $line );
    $cli->output( sprintf( 'cjw_tmv: container %d done in %.1fs', $node->attribute( 'node_id' ), microtime( true ) - $started ) );
    $usedIds = array_merge( $usedIds, $feed->listedIds() );
}
$cli->output( 'cjw_tmv: unused cache files removed: ' . cjwTmvFeed::removeUnused( $usedIds ) );

flock( $lock, LOCK_UN );
fclose( $lock );

?>
