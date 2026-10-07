<?php
/**
 * Imports the events of the TMV event database as content objects below their tmv_container, as the legacy
 * extension cjw_tmv_veranstdb did (TmvHelper::importEvent, the cronjobs import, update, remove_delayed and
 * remove_deleted), with the classes of the Nexus v2 TmvBundle package:
 *
 *   tmv_event      remote id cjw-tmv-<event id>                     title, sub_title, id, full_intro, body, contact,
 *                                                                   location, place, latitude, longitude, categories
 *     tmv_date     remote id cjw-tmv-<event id>-date-<date id>      start, end (one object per coming date)
 *     tmv_image    remote id cjw-tmv-<event id>-image-<medium id>   title, image (downloaded), caption (copyright)
 *   tmv_categorie  remote id cjw-tmv-category-<id>                  below the container's tmv_container_categories
 *
 * Objects are created in German (the TMV source language) and always available; an English title adds an eng-US
 * translation. A re-run updates instead of duplicating (remote ids). An event is updated when the TMV reports it
 * modified since the last run (FindEventIds eModifiedFrom), in place: its node and address stay.
 *
 * Removal, as the legacy cronjobs did and only ever below the container and only objects whose remote id starts
 * with "cjw-tmv-": dates that have ended, events without a coming date, and events the TMV no longer lists for the
 * container (only when the TMV answered). Objects are deleted, not put into the trash (legacy: removeNodeFromTree).
 *
 * At most limit_import_per_cronjob events (container field) are created or updated per run; at most limit_events
 * events are kept. Never throws; a failed API call changes nothing.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvImporter
{
    const PREFIX = 'cjw-tmv-';

    /** @var eZContentObjectTreeNode */
    protected $container;

    /** @var cjwTmvClient */
    protected $client;

    /** @var cjwTmvFeed */
    protected $feed;

    /** @var eZINI */
    protected $ini;

    /** @var int */
    protected $creatorID;

    /** @var bool */
    protected $dryRun = false;

    /** @var array counts and messages of the run (never personal data) */
    public $log = array();

    public function __construct( eZContentObjectTreeNode $container, cjwTmvClient $client, $dryRun = false )
    {
        $this->container = $container;
        $this->client = $client;
        $this->feed = new cjwTmvFeed( $container );
        $this->ini = eZINI::instance( 'cjw_tmv.ini' );
        $this->dryRun = (bool)$dryRun;
        $this->creatorID = (int)$this->setting( 'Import', 'CreatorUserID', 14 );
    }

    protected function setting( $group, $name, $default )
    {
        return $this->ini->hasVariable( $group, $name ) && $this->ini->variable( $group, $name ) !== ''
            ? $this->ini->variable( $group, $name ) : $default;
    }

    /* ---------------------------------------------------------------------------------------------------------
     * The run
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * @param int $limit events to create or update in this run (0 = the container's limit_import_per_cronjob)
     * @return bool false when the TMV could not be asked (nothing changed)
     */
    public function run( $limit = 0 )
    {
        $this->log = array();
        $budget = (int)$limit > 0 ? (int)$limit : $this->feed->limitPerRun();
        $runStarted = time();

        $ids = $this->fetchEventIds( array() );
        if ( $ids === false )
        {
            $this->log[] = 'event ids: TMV not reachable (' . $this->client->lastError . '), nothing changed';
            return false;
        }
        $ids = array_slice( $ids, 0, $this->feed->limitEvents() );
        $this->log[] = 'event ids at TMV: ' . count( $ids );

        $existing = $this->existingEvents();
        $this->log[] = 'events here: ' . count( $existing );

        // 1. remove what the TMV no longer lists, dates that have ended, events without a coming date
        $this->removeObsolete( $ids, $existing );
        $existing = $this->existingEvents();

        // 2. update events modified at TMV since the last run (legacy: cronjob update)
        $updated = 0;
        $lastRun = $this->lastRun();
        if ( $lastRun > 0 && $budget > 0 )
        {
            $modified = $this->fetchEventIds( array( 'eModifiedFrom' => date( 'Y-m-d', $lastRun ) ) );
            foreach ( $modified === false ? array() : $modified as $id )
            {
                if ( $budget <= 0 )
                    break;
                if ( !isset( $existing[$id] ) || !in_array( $id, $ids ) )
                    continue;
                $result = $this->importEvent( $id, $existing[$id] );
                if ( $result === 'updated' )
                {
                    $updated++;
                    $budget--;
                }
            }
        }

        // 3. import the events not here yet, in the TMV's date order (legacy: cronjob import)
        $created = $skipped = $failed = 0;
        $room = $this->feed->limitEvents() - count( $existing );
        foreach ( $ids as $id )
        {
            if ( $budget <= 0 || $room <= 0 )
                break;
            if ( isset( $existing[$id] ) )
                continue;
            $result = $this->importEvent( $id, null );
            if ( $result === 'created' )
            {
                $created++;
                $budget--;
                $room--;
            }
            elseif ( $result === 'skipped' )
                $skipped++;
            else
                $failed++;
        }
        $this->log[] = sprintf( 'events: %d created, %d updated, %d not shown (drafts, cancelled or past), %d failed',
                                $created, $updated, $skipped, $failed );
        if ( !$this->dryRun && $failed === 0 )
            $this->setLastRun( $runStarted );
        return true;
    }

    /* ---------------------------------------------------------------------------------------------------------
     * TMV
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * @param array $extra more request parameters (eModifiedFrom)
     * @return array|false event ids of the container's locations, client and extra ids (legacy: TmvHelper::getIds)
     */
    protected function fetchEventIds( array $extra )
    {
        $base = $extra + array( 'eStateIds' => array( 20, 40 ), 'eOrderFields' => 'DATE_START-ASC', 'eStartDate' => date( 'Y-m-d' ) );
        $ids = array();
        $answered = false;
        $locations = array_keys( $this->feed->locations() );
        if ( $locations )
        {
            $answer = $this->client->call( 'FindEventIds', $base + array( 'eAddrLocationIds' => $locations ) );
            if ( $answer !== false )
            {
                $answered = true;
                $ids = array_merge( $ids, self::idsOf( $answer ) );
            }
        }
        $clientId = $this->feed->clientId();
        if ( $clientId > 0 )
        {
            $answer = $this->client->call( 'FindEventIds', $base + array( 'eClientId' => $clientId ) );
            if ( $answer !== false )
            {
                $answered = true;
                $ids = array_merge( $ids, self::idsOf( $answer ) );
            }
        }
        if ( !$extra )
            $ids = array_merge( $ids, $this->feed->foreignEventIds() );
        if ( !$answered && ( $locations || $clientId > 0 ) )
            return false;
        return array_values( array_unique( $ids ) );
    }

    protected static function idsOf( array $answer )
    {
        $ids = array();
        foreach ( isset( $answer['eventIds'] ) ? (array)$answer['eventIds'] : array() as $item )
        {
            $id = is_array( $item ) ? ( isset( $item['id'] ) ? $item['id'] : 0 ) : $item;
            if ( (int)$id > 0 )
                $ids[] = (int)$id;
        }
        return $ids;
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Objects here
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * @return array TMV event id => tmv_event node (imported ones only, remote id cjw-tmv-<id>)
     */
    protected function existingEvents()
    {
        $result = array();
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( array(
            'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'tmv_event' ), 'Depth' => 1,
            'Limitation' => array(), 'IgnoreVisibility' => true, 'LoadDataMap' => false, 'Language' => false ),
            $this->container->attribute( 'node_id' ) );
        foreach ( is_array( $nodes ) ? $nodes : array() as $node )
        {
            $remoteId = $node->attribute( 'object' )->attribute( 'remote_id' );
            if ( preg_match( '/^' . self::PREFIX . '(\d+)$/', $remoteId, $m ) )
                $result[(int)$m[1]] = $node;
        }
        return $result;
    }

    /**
     * @return array remote id => node of the children of a node (only cjw-tmv-* ones)
     */
    protected static function importedChildren( eZContentObjectTreeNode $parent, $classIdentifier )
    {
        $result = array();
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( array(
            'ClassFilterType' => 'include', 'ClassFilterArray' => array( $classIdentifier ), 'Depth' => 1,
            'Limitation' => array(), 'IgnoreVisibility' => true, 'LoadDataMap' => true, 'Language' => false ),
            $parent->attribute( 'node_id' ) );
        foreach ( is_array( $nodes ) ? $nodes : array() as $node )
        {
            $remoteId = $node->attribute( 'object' )->attribute( 'remote_id' );
            if ( strpos( $remoteId, self::PREFIX ) === 0 )
                $result[$remoteId] = $node;
        }
        return $result;
    }

    /**
     * Deletes an imported node with its children. Refuses anything that is not below the container or whose
     * object was not made by this importer.
     */
    protected function remove( eZContentObjectTreeNode $node )
    {
        $object = $node->attribute( 'object' );
        if ( strpos( $object->attribute( 'remote_id' ), self::PREFIX ) !== 0
             || strpos( $node->attribute( 'path_string' ), $this->container->attribute( 'path_string' ) ) !== 0
             || $node->attribute( 'node_id' ) == $this->container->attribute( 'node_id' ) )
        {
            $this->log[] = 'REFUSED to remove node ' . $node->attribute( 'node_id' ) . ' (not an imported event object)';
            return false;
        }
        if ( $this->dryRun )
            return true;
        eZContentObjectTreeNode::removeSubtrees( array( $node->attribute( 'node_id' ) ), false );
        return true;
    }

    protected function removeObsolete( array $ids, array $existing )
    {
        $gone = $dates = $empty = 0;
        $now = time();
        foreach ( $existing as $id => $eventNode )
        {
            if ( !in_array( $id, $ids ) )
            {
                if ( $this->remove( $eventNode ) )
                    $gone++;
                continue;
            }
            $left = 0;
            foreach ( self::importedChildren( $eventNode, 'tmv_date' ) as $dateNode )
            {
                $dataMap = $dateNode->attribute( 'data_map' );
                $end = isset( $dataMap['end'] ) ? (int)$dataMap['end']->attribute( 'data_int' ) : 0;
                $start = isset( $dataMap['start'] ) ? (int)$dataMap['start']->attribute( 'data_int' ) : 0;
                if ( max( $start, $end ) < $now )
                {
                    if ( $this->remove( $dateNode ) )
                        $dates++;
                }
                else
                    $left++;
            }
            if ( $left === 0 && $this->remove( $eventNode ) )
                $empty++;
        }
        $this->log[] = sprintf( 'removed: %d events no longer at TMV, %d past dates, %d events without a coming date', $gone, $dates, $empty );
    }

    /* ---------------------------------------------------------------------------------------------------------
     * One event
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * @param int $id TMV event id
     * @param eZContentObjectTreeNode|null $node the existing event, null to create it
     * @return string created | updated | skipped | failed
     */
    protected function importEvent( $id, $node )
    {
        $answer = $this->client->call( 'FindEvent', array( 'objectId' => (int)$id ) );
        if ( $answer === false )
            return 'failed';
        $raw = isset( $answer['Event'] ) ? $answer['Event'] : $answer;
        $event = $this->normalize( $raw );
        if ( $event === false )
        {
            if ( $node )
                $this->remove( $node );
            return 'skipped';
        }
        if ( $this->dryRun )
            return $node ? 'updated' : 'created';

        $de = array(
            'title' => $event['title']['de'],
            'sub_title' => isset( $event['sub_title']['de'] ) ? $event['sub_title']['de'] : '',
            'id' => (string)$event['id'],
            'full_intro' => self::xml( isset( $event['short']['de'] ) ? $event['short']['de'] : '' ),
            'body' => self::xml( isset( $event['body']['de'] ) ? $event['body']['de'] : '' ),
            'contact' => self::xml( implode( '<br />', array_map( 'htmlspecialchars', $event['contact'] ) ) ),
            'location' => self::xml( implode( '<br />', array_map( 'htmlspecialchars', $event['location'] ) ) ),
            'place' => $event['place'],
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'categories' => implode( '-', $event['categories'] ) );

        $db = eZDB::instance();
        if ( $node )
        {
            $object = $node->attribute( 'object' );
            eZContentFunctions::updateAndPublishObject( $object, array( 'attributes' => $de, 'language' => 'ger-DE' ) );
            $object = eZContentObject::fetch( $object->attribute( 'id' ) );
        }
        else
        {
            $object = eZContentFunctions::createAndPublishObject( array(
                'creator_id' => $this->creatorID, 'class_identifier' => 'tmv_event',
                'parent_node_id' => $this->container->attribute( 'node_id' ), 'remote_id' => self::PREFIX . $event['id'],
                'language' => 'ger-DE', 'attributes' => $de ) );
        }
        if ( !$object instanceof eZContentObject )
            return 'failed';
        $object->setAlwaysAvailableLanguageID( $object->attribute( 'initial_language_id' ) );

        // English translation when the TMV has an English title (legacy: eng-GB version)
        if ( isset( $event['title']['en'] ) && $this->setting( 'Import', 'TranslationLanguage', '' ) !== '' )
        {
            $en = array( 'title' => $event['title']['en'],
                         'sub_title' => isset( $event['sub_title']['en'] ) ? $event['sub_title']['en'] : $de['sub_title'],
                         'full_intro' => isset( $event['short']['en'] ) ? self::xml( $event['short']['en'] ) : $de['full_intro'],
                         'body' => isset( $event['body']['en'] ) ? self::xml( $event['body']['en'] ) : $de['body'] );
            eZContentFunctions::updateAndPublishObject( $object, array( 'attributes' => $en + $de,
                'language' => $this->setting( 'Import', 'TranslationLanguage', 'eng-US' ) ) );
            $object = eZContentObject::fetch( $object->attribute( 'id' ) );
        }

        // the TMV's own dates (legacy: published = creationTime, modified = lastChangeTime)
        $db->begin();
        if ( $event['created'] > 0 && !$node )
            $object->setAttribute( 'published', $event['created'] );
        if ( $event['modified'] > 0 )
            $object->setAttribute( 'modified', $event['modified'] );
        $object->store();
        $db->commit();

        $eventNode = $object->attribute( 'main_node' );
        if ( !$eventNode )
            return 'failed';
        $this->syncDates( $eventNode, $event );
        $this->syncImages( $eventNode, $event, $raw );
        return $node ? 'updated' : 'created';
    }

    protected function syncDates( eZContentObjectTreeNode $eventNode, array $event )
    {
        $have = self::importedChildren( $eventNode, 'tmv_date' );
        $want = array();
        foreach ( $event['dates'] as $index => $date )
        {
            $remoteId = self::PREFIX . $event['id'] . '-date-' . ( $date['id'] > 0 ? $date['id'] : 's' . $date['start'] );
            $want[$remoteId] = true;
            $attributes = array( 'start' => (string)$date['start'], 'end' => (string)$date['end'] );
            if ( isset( $have[$remoteId] ) )
            {
                $dataMap = $have[$remoteId]->attribute( 'data_map' );
                if ( (int)$dataMap['start']->attribute( 'data_int' ) !== $date['start'] || (int)$dataMap['end']->attribute( 'data_int' ) !== $date['end'] )
                    eZContentFunctions::updateAndPublishObject( $have[$remoteId]->attribute( 'object' ), array( 'attributes' => $attributes, 'language' => 'ger-DE' ) );
                continue;
            }
            $object = eZContentFunctions::createAndPublishObject( array(
                'creator_id' => $this->creatorID, 'class_identifier' => 'tmv_date',
                'parent_node_id' => $eventNode->attribute( 'node_id' ), 'remote_id' => $remoteId,
                'language' => 'ger-DE', 'attributes' => $attributes ) );
            if ( $object instanceof eZContentObject )
                $object->setAlwaysAvailableLanguageID( $object->attribute( 'initial_language_id' ) );
        }
        foreach ( $have as $remoteId => $node )
            if ( !isset( $want[$remoteId] ) )
                $this->remove( $node );
    }

    protected function syncImages( eZContentObjectTreeNode $eventNode, array $event, array $raw )
    {
        $have = self::importedChildren( $eventNode, 'tmv_image' );
        $want = array();
        $max = (int)$this->setting( 'Images', 'MaxPerEvent', 4 );
        $media = isset( $raw['media'] ) && is_array( $raw['media'] ) ? $raw['media'] : array();
        usort( $media, function ( $a, $b ) {
            return ( isset( $a['sortingValue'] ) ? (int)$a['sortingValue'] : 0 ) <=> ( isset( $b['sortingValue'] ) ? (int)$b['sortingValue'] : 0 );
        } );
        $tmpDir = eZSys::cacheDirectory() . '/cjw_tmv';
        foreach ( $media as $medium )
        {
            if ( count( $want ) >= $max )
                break;
            if ( !is_array( $medium ) || empty( $medium['deeplink'] ) || !empty( $medium['deactivated'] ) || empty( $medium['id'] ) )
                continue;
            $remoteId = self::PREFIX . $event['id'] . '-image-' . (int)$medium['id'];
            if ( isset( $have[$remoteId] ) )
            {
                $want[$remoteId] = true;
                continue;
            }
            $bytes = $this->client->download( $medium['deeplink'] );
            if ( $bytes === false || !@getimagesizefromstring( $bytes ) )
                continue;
            $bytes = self::scale( $bytes, (int)$this->setting( 'Images', 'MaxWidth', 1200 ) );
            if ( $bytes === false )
                continue;
            if ( !is_dir( $tmpDir ) )
                @mkdir( $tmpDir, 0775, true );
            $file = $tmpDir . '/' . (int)$event['id'] . '-' . (int)$medium['id'] . '.jpg';
            file_put_contents( $file, $bytes );
            $title = isset( $medium['pooledMedium']['title']['de'] ) && is_string( $medium['pooledMedium']['title']['de'] ) && trim( $medium['pooledMedium']['title']['de'] ) !== ''
                ? trim( $medium['pooledMedium']['title']['de'] ) : $event['title']['de'];
            $copyright = isset( $medium['pooledMedium']['copyright']['de'] ) && is_string( $medium['pooledMedium']['copyright']['de'] )
                ? trim( $medium['pooledMedium']['copyright']['de'] ) : '';
            $object = eZContentFunctions::createAndPublishObject( array(
                'creator_id' => $this->creatorID, 'class_identifier' => 'tmv_image',
                'parent_node_id' => $eventNode->attribute( 'node_id' ), 'remote_id' => $remoteId, 'language' => 'ger-DE',
                'attributes' => array( 'title' => $title, 'image' => $file . '|' . $title,
                                       'caption' => self::xml( $copyright !== '' ? '&copy; ' . htmlspecialchars( $copyright ) : '' ) ) ) );
            @unlink( $file );
            if ( $object instanceof eZContentObject )
            {
                $object->setAlwaysAvailableLanguageID( $object->attribute( 'initial_language_id' ) );
                $want[$remoteId] = true;
            }
        }
        foreach ( $have as $remoteId => $node )
            if ( !isset( $want[$remoteId] ) )
                $this->remove( $node );
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Categories (legacy: cronjob import_categories)
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * Creates or renames the tmv_categorie objects below the container's tmv_container_categories node.
     */
    public function importCategories()
    {
        $parent = eZContentObjectTreeNode::subTreeByNodeID( array(
            'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'tmv_container_categories' ), 'Depth' => 1,
            'Limitation' => array(), 'IgnoreVisibility' => true, 'Limit' => 1, 'Language' => false ), $this->container->attribute( 'node_id' ) );
        if ( !$parent )
        {
            $this->log[] = 'categories: no tmv_container_categories below the container, skipped';
            return;
        }
        $parent = $parent[0];
        $answer = $this->client->call( 'FindCategories' );
        if ( $answer === false || !isset( $answer['categories'] ) )
        {
            $this->log[] = 'categories: TMV not reachable, nothing changed';
            return;
        }
        $have = self::importedChildren( $parent, 'tmv_categorie' );
        $created = $renamed = 0;
        foreach ( (array)$answer['categories'] as $item )
        {
            if ( !is_array( $item ) || empty( $item['id'] ) )
                continue;
            $name = '';
            foreach ( array( 'i18nName', 'name', 'title' ) as $key )
            {
                if ( isset( $item[$key]['de'] ) && is_string( $item[$key]['de'] ) ) { $name = trim( $item[$key]['de'] ); break; }
                if ( isset( $item[$key] ) && is_string( $item[$key] ) ) { $name = trim( $item[$key] ); break; }
            }
            if ( $name === '' )
                continue;
            $remoteId = self::PREFIX . 'category-' . (int)$item['id'];
            if ( $this->dryRun )
                continue;
            if ( isset( $have[$remoteId] ) )
            {
                $dataMap = $have[$remoteId]->attribute( 'data_map' );
                if ( $dataMap['title']->attribute( 'data_text' ) !== $name )
                {
                    eZContentFunctions::updateAndPublishObject( $have[$remoteId]->attribute( 'object' ), array( 'attributes' => array( 'title' => $name ), 'language' => 'ger-DE' ) );
                    $renamed++;
                }
                continue;
            }
            $object = eZContentFunctions::createAndPublishObject( array(
                'creator_id' => $this->creatorID, 'class_identifier' => 'tmv_categorie',
                'parent_node_id' => $parent->attribute( 'node_id' ), 'remote_id' => $remoteId, 'language' => 'ger-DE',
                'attributes' => array( 'title' => $name, 'id' => (string)(int)$item['id'] ) ) );
            if ( $object instanceof eZContentObject )
            {
                $object->setAlwaysAvailableLanguageID( $object->attribute( 'initial_language_id' ) );
                $created++;
            }
        }
        $this->log[] = "categories: $created created, $renamed renamed, " . count( $have ) . ' were here';
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Last run (eZSiteData, no new version of the container per run)
     * ------------------------------------------------------------------------------------------------------- */

    protected function lastRunKey()
    {
        return 'cjw_tmv_last_run_' . (int)$this->container->attribute( 'node_id' );
    }

    protected function lastRun()
    {
        $row = eZSiteData::fetchByName( $this->lastRunKey() );
        return $row ? (int)$row->attribute( 'value' ) : 0;
    }

    protected function setLastRun( $timestamp )
    {
        $row = eZSiteData::fetchByName( $this->lastRunKey() );
        if ( !$row )
            $row = eZSiteData::create( $this->lastRunKey(), (string)$timestamp );
        $row->setAttribute( 'value', (string)$timestamp );
        $row->store();
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * Turns one API event into the field values (legacy: TmvHelper::importEvent).
     *
     * @return array|false false for an event that is not shown: a draft without the internet prefix, a cancelled
     *                     event, an event without a coming date
     */
    public function normalize( array $raw )
    {
        $get = function ( $path ) use ( $raw ) {
            $value = $raw;
            foreach ( explode( '.', $path ) as $key )
            {
                if ( !is_array( $value ) || !isset( $value[$key] ) )
                    return null;
                $value = $value[$key];
            }
            return $value;
        };
        $i18n = function ( $path, $html = false ) use ( $get ) {
            $out = array();
            foreach ( array( 'de', 'en' ) as $lang )
            {
                $value = $get( $path . '.' . $lang );
                if ( is_string( $value ) && trim( $value ) !== '' )
                    $out[$lang] = $html ? cjwTmvHtml::clean( $value ) : trim( html_entity_decode( strip_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
            }
            return $out;
        };

        $id = (int)$get( 'id' );
        if ( $id <= 0 || $get( 'cancelled' ) )
            return false;
        $title = $i18n( 'title' );
        if ( (int)$get( 'entityState.key' ) === 20 )
        {
            $prefix = (string)$this->setting( 'Import', 'OnlyInternetPrefix', '' );
            if ( $prefix === '' || !isset( $title['de'] ) || strpos( $title['de'], $prefix ) === false )
                return false;
            foreach ( $title as $lang => $text )
                $title[$lang] = trim( str_replace( $prefix, '', $text ) );
        }
        if ( !isset( $title['de'] ) || $title['de'] === '' )
        {
            if ( !isset( $title['en'] ) )
                return false;
            $title['de'] = $title['en'];
        }

        $cancelledIds = array();
        foreach ( (array)$get( 'cancelledEventDates' ) as $item )
            if ( is_array( $item ) && isset( $item['id'] ) )
                $cancelledIds[] = (int)$item['id'];
        $dates = array();
        $now = time();
        $maxDates = (int)$this->setting( 'Import', 'MaxDatesPerEvent', 0 );
        foreach ( (array)$get( 'eventDates' ) as $item )
        {
            if ( !is_array( $item ) || empty( $item['date'] ) )
                continue;
            $dateId = isset( $item['id'] ) ? (int)$item['id'] : 0;
            if ( in_array( $dateId, $cancelledIds ) )
                continue;
            $start = strtotime( substr( (string)$item['date'], 0, 10 ) . ( !empty( $item['startTime'] ) ? ' ' . $item['startTime'] : '' ) );
            if ( !$start )
                continue;
            $end = $start + 60 * max( 0, (int)( isset( $item['duration'] ) ? $item['duration'] : 0 ) );
            if ( $end < $now )
                continue;
            $dates[] = array( 'id' => $dateId, 'start' => $start, 'end' => $end );
        }
        if ( !$dates )
            return false;
        usort( $dates, function ( $a, $b ) { return $a['start'] <=> $b['start']; } );
        if ( $maxDates > 0 )
            $dates = array_slice( $dates, 0, $maxDates );

        $categories = array();
        foreach ( (array)$get( 'categories' ) as $item )
            if ( is_array( $item ) && isset( $item['id'] ) )
                $categories[] = (int)$item['id'];

        $locations = $this->feed->locations();
        $place = '';
        $contact = self::addressLines( (array)$get( 'contributor' ), $locations, $place );
        $location = self::addressLines( (array)$get( 'location' ), $locations, $place );
        if ( !$location )
            $location = $contact;
        $lat = $lon = '';
        foreach ( array( 'location.geoInfo.coordinates', 'contributor.geoInfo.coordinates', 'geoInfo.coordinates' ) as $path )
        {
            if ( $lat === '' && is_numeric( $get( $path . '.latitude' ) ) && (float)$get( $path . '.latitude' ) != 0 )
            {
                $lat = (string)$get( $path . '.latitude' );
                $lon = (string)$get( $path . '.longitude' );
            }
        }

        return array(
            'id' => $id,
            'created' => (int)strtotime( (string)$get( 'creationTime' ) ),
            'modified' => (int)strtotime( (string)$get( 'lastChangeTime' ) ),
            'title' => $title,
            'sub_title' => $i18n( 'subTitle' ),
            'short' => $i18n( 'shortDescription', true ),
            'body' => $i18n( 'longDescription', true ),
            'categories' => $categories,
            'place' => $place,
            'contact' => $contact,
            'location' => $location,
            'latitude' => $lat,
            'longitude' => $lon,
            'dates' => $dates );
    }

    /**
     * @return array address lines (name, person, street, zip + city, phone, fax, email, homepage) of a contributor
     *               or venue; $place is set to the container's name of its location, else its city
     */
    protected static function addressLines( array $entry, array $locations, &$place )
    {
        $c = isset( $entry['contact1'] ) && is_array( $entry['contact1'] ) ? $entry['contact1'] : array();
        $a = isset( $c['address'] ) && is_array( $c['address'] ) ? $c['address'] : array();
        $s = function ( $array, $key ) {
            return isset( $array[$key] ) && is_scalar( $array[$key] ) ? trim( (string)$array[$key] ) : '';
        };
        $lines = array();
        if ( $s( $c, 'contactName' ) !== '' )
            $lines[] = $s( $c, 'contactName' );
        if ( $s( $c, 'lastname' ) !== '' && ( $s( $c, 'firstname' ) !== '' || $s( $c, 'contactName' ) !== $s( $c, 'lastname' ) ) )
            $lines[] = trim( $s( $c, 'salutation' ) . ' ' . $s( $c, 'firstname' ) . ' ' . $s( $c, 'lastname' ) );
        if ( $s( $a, 'street' ) !== '' )
            $lines[] = trim( $s( $a, 'street' ) . ' ' . $s( $a, 'streetNo' ) );
        if ( $s( $a, 'city' ) !== '' )
        {
            $lines[] = trim( $s( $a, 'zipcode' ) . ' ' . $s( $a, 'city' ) );
            $locationId = isset( $entry['location']['id'] ) ? (int)$entry['location']['id'] : 0;
            $place = isset( $locations[$locationId] ) ? $locations[$locationId] : $s( $a, 'city' );
        }
        foreach ( array( 'phone1', 'fax', 'email' ) as $key )
            if ( $s( $a, $key ) !== '' )
                $lines[] = $s( $a, $key );
        if ( isset( $a['homepage']['de'] ) && is_string( $a['homepage']['de'] ) && trim( $a['homepage']['de'] ) !== '' )
            $lines[] = trim( $a['homepage']['de'] );
        return array_values( array_unique( $lines ) );
    }

    /**
     * @param string $html clean HTML (cjwTmvHtml) or ''
     * @return string the ezxmltext input (legacy: TmvHelper::stringToXML)
     */
    public static function xml( $html )
    {
        $html = trim( (string)$html );
        $parser = new eZSimplifiedXMLInputParser( null );
        $parser->setParseLineBreaks( true );
        $document = $parser->process( $html );
        if ( !$document )
        {
            $parser = new eZSimplifiedXMLInputParser( null );
            $document = $parser->process( htmlspecialchars( strip_tags( $html ), ENT_NOQUOTES ) );
        }
        return $document ? eZXMLTextType::domString( $document ) : '';
    }

    /**
     * @return string|false JPEG bytes no wider than $width
     */
    protected static function scale( $bytes, $width )
    {
        $info = @getimagesizefromstring( $bytes );
        if ( !function_exists( 'imagecreatefromstring' ) )
            return ( $info && $info[2] === IMAGETYPE_JPEG ) ? $bytes : false;
        $image = @imagecreatefromstring( $bytes );
        if ( !$image )
            return false;
        $w = imagesx( $image );
        $h = imagesy( $image );
        if ( $w > $width )
        {
            $scaled = imagescale( $image, $width, (int)round( $h * $width / $w ), IMG_BICUBIC );
            imagedestroy( $image );
            $image = $scaled;
        }
        ob_start();
        imagejpeg( $image, null, 85 );
        imagedestroy( $image );
        return ob_get_clean();
    }
}

?>
