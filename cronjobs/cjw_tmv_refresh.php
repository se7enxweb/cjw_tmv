<?php
/**
 * Cronjob part cjw_tmv: imports and updates the TMV events of every tmv_container as content objects
 * (cjwTmvImporter), and removes the past ones.
 *
 *   php runcronjobs.php -s <German siteaccess> cjw_tmv
 *
 * Run it as the web server's user (files of the event images) with the German siteaccess (the TMV source
 * language). Environment: CJW_TMV_LIMIT=<n> caps the events created or updated per container in this run (the
 * container's limit_import_per_cronjob otherwise), CJW_TMV_DRY_RUN=1 asks the TMV and writes nothing, CJW_TMV_UPDATE_ALL=1 updates every existing event (within the limit).
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
    $cli->warning( 'cjw_tmv: no TMV account in cjw_tmv.ini [TMV] User/Password (settings/override), nothing imported' );
    return;
}

$lockDir = eZSys::cacheDirectory() . '/cjw_tmv';
if ( !is_dir( $lockDir ) )
    cjwTmvImporter::makeDirectory( $lockDir, 0775 );
$lock = @fopen( $lockDir . '/import.lock', 'c' );
if ( !$lock || !flock( $lock, LOCK_EX | LOCK_NB ) )
{
    $cli->warning( 'cjw_tmv: another import is running, skipped' );
    return;
}

$creatorID = (int)eZINI::instance( 'cjw_tmv.ini' )->variable( 'Import', 'CreatorUserID' );
$creator = eZUser::fetch( $creatorID > 0 ? $creatorID : 14 );
if ( $creator )
    eZUser::setCurrentlyLoggedInUser( $creator, $creator->attribute( 'contentobject_id' ) );

$limit = (int)getenv( 'CJW_TMV_LIMIT' );
$dryRun = getenv( 'CJW_TMV_DRY_RUN' ) === '1';
foreach ( cjwTmvFeed::fetchContainers() as $node )
{
    $started = microtime( true );
    $importer = new cjwTmvImporter( $node, $client, $dryRun );
    $importer->importCategories();
    $importer->run( $limit, getenv( 'CJW_TMV_UPDATE_ALL' ) === '1' );
    foreach ( $importer->log as $line )
        $cli->output( 'cjw_tmv: container ' . $node->attribute( 'node_id' ) . ': ' . $line );
    $cli->output( sprintf( 'cjw_tmv: container %d done in %.1fs%s', $node->attribute( 'node_id' ), microtime( true ) - $started, $dryRun ? ' (dry run)' : '' ) );
}

flock( $lock, LOCK_UN );
fclose( $lock );

?>
