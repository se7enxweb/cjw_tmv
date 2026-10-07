<?php
/**
 * The TMV event feed of one event container (a tmv_container node): refreshed from the TMV API by the cronjob,
 * read by the pages from a file cache only.
 *
 * The cache lives below the var directory's cache (cjw_tmv.ini [Cache] Directory, default <var>/cache/cjw_tmv):
 *   list-<container node>.json   the container's events in date order, a summary each (what a list draws)
 *   events/<event id>.json       one event, everything the detail page draws
 *   categories.json              the TMV categories (id => name per language)
 * The event images are downloaded (and scaled down) into <var>/cache/public/cjw_tmv/images, which the web server
 * serves directly, so a visitor's browser never asks the TMV servers for anything.
 *
 * A page never calls the API. When the cache is missing, a page shows an empty list; when the API fails during a
 * refresh, the previous files stay in place and keep being shown. Nothing here throws.
 *
 * Port of the import of the legacy extension cjw_tmv_veranstdb (TmvHelper::getIds, importEvent) and the list logic
 * of the Nexus v2 block tmv_events (TmvEventsHandler::fetchEventsByFilter), without content objects.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvFeed
{
    /** @var eZContentObjectTreeNode */
    protected $container;

    /** @var eZINI */
    protected $ini;

    /** @var array the decoded list file, loaded once */
    protected $listData = null;

    /** @var array messages of the last refresh (counts only, never personal data) */
    public $log = array();

    /**
     * @param eZContentObjectTreeNode $container a tmv_container node
     */
    public function __construct( eZContentObjectTreeNode $container )
    {
        $this->container = $container;
        $this->ini = eZINI::instance( 'cjw_tmv.ini' );
    }

    /**
     * @param mixed $container a node, a node id or an "ezlocation://<id>" value
     * @return cjwTmvFeed|false
     */
    public static function forContainer( $container )
    {
        if ( is_string( $container ) && preg_match( '#(\d+)\s*$#', $container, $m ) )
            $container = (int)$m[1];
        if ( is_numeric( $container ) )
            $container = eZContentObjectTreeNode::fetch( (int)$container );
        if ( !$container instanceof eZContentObjectTreeNode || $container->attribute( 'class_identifier' ) !== 'tmv_container' )
            return false;
        return new self( $container );
    }

    /**
     * @return eZContentObjectTreeNode[] every tmv_container node of the installation
     */
    public static function fetchContainers()
    {
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( array(
            'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'tmv_container' ),
            'Limitation' => array(), 'IgnoreVisibility' => true, 'Language' => false, 'MainNodeOnly' => true ), 1 );
        return is_array( $nodes ) ? $nodes : array();
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------------------------------------------- */

    protected function setting( $group, $name, $default )
    {
        return $this->ini->hasVariable( $group, $name ) && $this->ini->variable( $group, $name ) !== ''
            ? $this->ini->variable( $group, $name ) : $default;
    }

    /**
     * @param string $identifier attribute of the container
     * @return string the attribute's text, '' when the class has no such attribute or it is empty
     */
    protected function containerText( $identifier )
    {
        $dataMap = $this->container->attribute( 'data_map' );
        if ( !isset( $dataMap[$identifier] ) || !$dataMap[$identifier]->attribute( 'has_content' ) )
            return '';
        return trim( (string)$dataMap[$identifier]->attribute( 'data_text' ) );
    }

    /**
     * @return array location id => place name, from the container's "locations" field (lines "id|name") or the INI
     */
    public function locations()
    {
        $result = array();
        foreach ( preg_split( '/\R/', $this->containerText( 'locations' ) ) as $line )
        {
            $parts = explode( '|', trim( $line ), 2 );
            if ( count( $parts ) === 2 && (int)$parts[0] > 0 )
                $result[(int)$parts[0]] = trim( $parts[1] );
        }
        if ( !$result && $this->ini->hasVariable( 'Locations', 'Location' ) )
        {
            foreach ( (array)$this->ini->variable( 'Locations', 'Location' ) as $id => $name )
                if ( (int)$id > 0 )
                    $result[(int)$id] = $name;
        }
        return $result;
    }

    /**
     * @return int the TMV client id (container field client_id, else the INI), 0 for none
     */
    public function clientId()
    {
        $id = (int)$this->containerText( 'client_id' );
        return $id > 0 ? $id : (int)$this->setting( 'Client', 'ClientId', 0 );
    }

    /**
     * @return int events of this container at most (field limit_events, default 1000)
     */
    public function limitEvents()
    {
        $limit = (int)$this->containerText( 'limit_events' );
        return $limit > 0 ? $limit : (int)$this->setting( 'Import', 'LimitEvents', 1000 );
    }

    /**
     * @return int event downloads per refresh at most (field limit_import_per_cronjob, default 100)
     */
    public function limitPerRun()
    {
        $limit = (int)$this->containerText( 'limit_import_per_cronjob' );
        return $limit > 0 ? $limit : (int)$this->setting( 'Import', 'LimitPerRun', 100 );
    }

    /**
     * @return int events per list page (field page_limit, else [List] PageLimit, default 20)
     */
    public function pageLimit()
    {
        $limit = (int)$this->containerText( 'page_limit' );
        return $limit > 0 ? $limit : (int)$this->setting( 'List', 'PageLimit', 20 );
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Cache files
     * ------------------------------------------------------------------------------------------------------- */

    public static function cacheDir()
    {
        $ini = eZINI::instance( 'cjw_tmv.ini' );
        $sub = $ini->hasVariable( 'Cache', 'Directory' ) && $ini->variable( 'Cache', 'Directory' ) !== ''
            ? trim( $ini->variable( 'Cache', 'Directory' ), '/' ) : 'cjw_tmv';
        return eZSys::cacheDirectory() . '/' . $sub;
    }

    public static function imageDir()
    {
        return eZSys::cacheDirectory() . '/public/cjw_tmv/images';
    }

    protected function listFile()
    {
        return self::cacheDir() . '/list-' . (int)$this->container->attribute( 'node_id' ) . '.json';
    }

    protected static function eventFile( $id )
    {
        return self::cacheDir() . '/events/' . (int)$id . '.json';
    }

    protected static function readJson( $file )
    {
        if ( !is_file( $file ) || !is_readable( $file ) )
            return false;
        $data = json_decode( (string)@file_get_contents( $file ), true );
        return is_array( $data ) ? $data : false;
    }

    /**
     * Writes atomically (temporary file + rename), readable by the web server whoever runs the cronjob.
     */
    protected static function writeFile( $file, $content )
    {
        $dir = dirname( $file );
        if ( !is_dir( $dir ) && !@mkdir( $dir, 0775, true ) && !is_dir( $dir ) )
            return false;
        $tmp = $file . '.' . getmypid() . '.tmp';
        if ( @file_put_contents( $tmp, $content ) === false )
            return false;
        @chmod( $tmp, 0664 );
        return @rename( $tmp, $file );
    }

    protected static function writeJson( $file, array $data )
    {
        return self::writeFile( $file, json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Reading (pages)
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * @return string 'de' or 'en', from the siteaccess' content language
     */
    public static function language()
    {
        $locale = eZINI::instance( 'site.ini' )->variable( 'RegionalSettings', 'ContentObjectLocale' );
        return strpos( (string)$locale, 'ger' ) === 0 ? 'de' : 'en';
    }

    protected static function text( $value )
    {
        if ( !is_array( $value ) )
            return (string)$value;
        $lang = self::language();
        if ( isset( $value[$lang] ) && trim( $value[$lang] ) !== '' )
            return $value[$lang];
        return isset( $value['de'] ) ? (string)$value['de'] : '';
    }

    protected function loadList()
    {
        if ( $this->listData === null )
        {
            $data = self::readJson( $this->listFile() );
            $this->listData = $data && isset( $data['events'] ) ? $data : array( 'built' => 0, 'events' => array() );
        }
        return $this->listData;
    }

    /**
     * @return int when the list was built (0 = never)
     */
    public function built()
    {
        $list = $this->loadList();
        return (int)$list['built'];
    }

    /**
     * @return array the filter of the current request (v2: TmvEventsHandler::setFilterParams), GET or POST:
     *               Keyword, Start, End (Y-m-d), Categories[], Places[], HideEventsWithMultipleDates
     */
    public static function filterParams()
    {
        $http = eZHTTPTool::instance();
        $get = function ( $name ) use ( $http ) {
            if ( $http->hasPostVariable( $name ) ) return $http->postVariable( $name );
            if ( $http->hasGetVariable( $name ) ) return $http->getVariable( $name );
            return null;
        };
        $string = function ( $value, $pattern = null ) {
            $value = is_string( $value ) ? trim( strip_tags( $value ) ) : '';
            $value = mb_substr( $value, 0, 100 );
            return ( $pattern && !preg_match( $pattern, $value ) ) ? '' : $value;
        };
        $list = function ( $value ) {
            $out = array();
            foreach ( (array)$value as $item )
                if ( is_string( $item ) && trim( $item ) !== '' )
                    $out[] = mb_substr( trim( strip_tags( $item ) ), 0, 100 );
            return array_values( array_unique( $out ) );
        };
        $params = array(
            'keyword' => $string( $get( 'Keyword' ) ),
            'start' => $string( $get( 'Start' ), '/^\d{4}-\d{2}-\d{2}$/' ),
            'end' => $string( $get( 'End' ), '/^\d{4}-\d{2}-\d{2}$/' ),
            'categories' => $list( $get( 'Categories' ) ),
            'places' => $list( $get( 'Places' ) ),
            'hide_events_with_multiple_dates' => (bool)$get( 'HideEventsWithMultipleDates' ) );
        // the filter as a query string (for the pager), without the page
        $query = array();
        foreach ( array( 'Keyword' => 'keyword', 'Start' => 'start', 'End' => 'end' ) as $name => $key )
            if ( $params[$key] !== '' )
                $query[$name] = $params[$key];
        if ( $params['categories'] ) $query['Categories'] = $params['categories'];
        if ( $params['places'] ) $query['Places'] = $params['places'];
        $params['query'] = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        return $params;
    }

    /**
     * @return string the text of an HTML snippet, cut at a word boundary to $length characters at most
     */
    public static function plain( $html, $length )
    {
        $text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( str_replace( array( '<br />', '</p>' ), ' ', (string)$html ) ), ENT_QUOTES, 'UTF-8' ) ) );
        if ( mb_strlen( $text ) <= $length )
            return $text;
        $cut = mb_substr( $text, 0, $length );
        $space = mb_strrpos( $cut, ' ' );
        return rtrim( $space > $length / 2 ? mb_substr( $cut, 0, $space ) : $cut, ' ,.;:-' ) . '...';
    }

    /**
     * The container's events that match the filter, next date first.
     *
     * @param array $filter see filterParams()
     * @param int $offset
     * @param int $limit 0 = page limit of the container
     * @param array $excludeCategories category ids left out unless the filter asks for them (v2 block parameter)
     * @return array list (each: id, title, sub_title, short, place, location, categories, next_start, next_end,
     *               date_count, image, cancelled), total, built
     */
    public function events( array $filter = array(), $offset = 0, $limit = 0, array $excludeCategories = array() )
    {
        $filter = array_merge( array( 'keyword' => '', 'start' => '', 'end' => '', 'categories' => array(),
                                      'places' => array(), 'hide_events_with_multiple_dates' => false ), $filter );
        $limit = (int)$limit > 0 ? (int)$limit : $this->pageLimit();
        $now = time();
        $from = $filter['start'] !== '' ? strtotime( $filter['start'] . ' 00:00:00' ) : $now;
        $to = $filter['end'] !== '' ? strtotime( $filter['end'] . ' 23:59:59' ) : PHP_INT_MAX;
        $categories = array_map( 'intval', array_filter( $filter['categories'], 'is_numeric' ) );
        $all = in_array( 'all', $filter['categories'] ) || !$categories;
        $exclude = array_diff( array_map( 'intval', array_filter( array_map( 'trim', $excludeCategories ), 'is_numeric' ) ), $categories );
        $keyword = mb_strtolower( $filter['keyword'] );

        $matches = array();
        $list = $this->loadList();
        foreach ( $list['events'] as $event )
        {
            if ( $filter['places'] && !in_array( $event['place'], $filter['places'], true ) )
                continue;
            if ( !$all && !array_intersect( $categories, $event['categories'] ) )
                continue;
            if ( $exclude && array_intersect( $exclude, $event['categories'] ) )
                continue;
            if ( $keyword !== '' )
            {
                $haystack = mb_strtolower( implode( ' ', array( self::text( $event['title'] ), self::text( $event['sub_title'] ),
                    strip_tags( self::text( $event['short'] ) ), $event['place'] ) ) );
                if ( mb_strpos( $haystack, $keyword ) === false )
                    continue;
            }
            $dates = array();
            foreach ( $event['dates'] as $date )
            {
                if ( $date['end'] >= $from && $date['start'] <= $to && $date['end'] >= $now )
                    $dates[] = $date;
            }
            if ( !$dates )
                continue;
            if ( $filter['hide_events_with_multiple_dates'] && count( $dates ) >= 3 )
                continue;
            $matches[] = array(
                'id' => (int)$event['id'],
                'title' => self::text( $event['title'] ),
                'sub_title' => self::text( $event['sub_title'] ),
                'short' => self::text( $event['short'] ),
                'short_text' => self::plain( self::text( $event['short'] ), 260 ),
                'place' => $event['place'],
                'location' => $event['location'],
                'categories' => $event['categories'],
                'next_start' => (int)$dates[0]['start'],
                'next_end' => (int)$dates[0]['end'],
                'date_count' => count( $dates ),
                'image' => self::imageUrl( $event['image'] ),
                'cancelled' => !empty( $event['cancelled'] ) || !empty( $dates[0]['cancelled'] ) );
        }
        usort( $matches, function ( $a, $b ) {
            return $a['next_start'] <=> $b['next_start'] ?: $a['id'] <=> $b['id'];
        } );
        return array( 'list' => array_slice( $matches, max( 0, (int)$offset ), $limit ), 'total' => count( $matches ),
                      'built' => (int)$list['built'], 'limit' => $limit );
    }

    /**
     * @return array place names of the container's events (v2: distinct "place" values), sorted
     */
    public function places()
    {
        $list = $this->loadList();
        $places = array();
        foreach ( $list['events'] as $event )
            if ( $event['place'] !== '' )
                $places[$event['place']] = true;
        $places = array_keys( $places );
        sort( $places, SORT_STRING | SORT_FLAG_CASE );
        return $places;
    }

    /**
     * @return array category id => name of the categories used by the container's events (v2 lists the imported
     *               categories, sorted by name)
     */
    public function categories()
    {
        $names = self::readJson( self::cacheDir() . '/categories.json' );
        $names = $names && isset( $names['categories'] ) ? $names['categories'] : array();
        $list = $this->loadList();
        $result = array();
        foreach ( $list['events'] as $event )
        {
            foreach ( $event['categories'] as $id )
            {
                if ( !isset( $result[$id] ) )
                    $result[$id] = isset( $names[$id] ) ? self::text( $names[$id] ) : (string)$id;
            }
        }
        asort( $result, SORT_STRING | SORT_FLAG_CASE );
        return $result;
    }

    /**
     * One event of this container, for its detail page.
     *
     * @param int $id the TMV event id
     * @return array|false title, sub_title, short, body, contact (lines), location (lines), place, latitude,
     *                     longitude, dates (start, end, cancelled; the coming ones), images (url, title, copyright),
     *                     link, cancelled
     */
    public function event( $id )
    {
        $id = (int)$id;
        $list = $this->loadList();
        $known = false;
        foreach ( $list['events'] as $item )
        {
            if ( (int)$item['id'] === $id )
            {
                $known = true;
                break;
            }
        }
        $data = $known ? self::readJson( self::eventFile( $id ) ) : false;
        if ( !$data || empty( $data['event'] ) )
            return false;
        $event = $data['event'];
        $now = time();
        $dates = array();
        foreach ( $event['dates'] as $date )
            if ( $date['end'] >= $now )
                $dates[] = $date;
        $images = array();
        foreach ( $event['images'] as $image )
        {
            $url = self::imageUrl( $image['file'] );
            if ( $url !== '' )
                $images[] = array( 'url' => $url, 'title' => $image['title'], 'copyright' => $image['copyright'] );
        }
        return array(
            'id' => $id,
            'title' => self::text( $event['title'] ),
            'sub_title' => self::text( $event['sub_title'] ),
            'short' => self::text( $event['short'] ),
            'body' => self::text( $event['body'] ),
            'contact' => $event['contact'],
            'location' => $event['location'],
            'place' => $event['place'],
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'dates' => $dates,
            'images' => $images,
            'link' => $event['link'],
            'cancelled' => !empty( $event['cancelled'] ) );
    }

    /**
     * @param string $file a file name in the image directory
     * @return string the address of the image below the site root ('' when it is not there)
     */
    protected static function imageUrl( $file )
    {
        if ( !is_string( $file ) || $file === '' || !is_file( self::imageDir() . '/' . $file ) )
            return '';
        return '/' . ltrim( self::imageDir(), '/' ) . '/' . $file;
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Refresh (cronjob)
     * ------------------------------------------------------------------------------------------------------- */

    /**
     * Refreshes this container's list from the API. Keeps the previous list when the API cannot be reached.
     *
     * @param cjwTmvClient $client
     * @param bool $force fetch every event again, ignoring EventMaxAge
     * @return bool whether a new list was written
     */
    public function refresh( cjwTmvClient $client, $force = false )
    {
        $this->log = array();
        $ids = $this->fetchEventIds( $client );
        if ( $ids === false )
        {
            $this->log[] = 'event ids: API not reachable (' . $client->lastError . '), previous list kept';
            return false;
        }
        $ids = array_slice( $ids, 0, $this->limitEvents() );
        $this->log[] = 'event ids: ' . count( $ids );

        // fetch what is missing first, then the oldest, within the budget of one run
        $maxAge = (int)$this->setting( 'Import', 'EventMaxAge', 21600 );
        $due = array();
        foreach ( $ids as $id )
        {
            $file = self::eventFile( $id );
            $age = is_file( $file ) ? time() - filemtime( $file ) : PHP_INT_MAX;
            if ( $force || $age > $maxAge )
                $due[$id] = $age;
        }
        arsort( $due );
        $budget = $this->limitPerRun();
        $fetched = $skipped = $failed = $images = 0;
        foreach ( array_keys( $due ) as $id )
        {
            if ( $budget-- <= 0 )
                break;
            $answer = $client->call( 'FindEvent', array( 'objectId' => $id ) );
            if ( $answer === false )
            {
                $failed++;
                continue;
            }
            $raw = isset( $answer['Event'] ) ? $answer['Event'] : $answer;
            $event = $this->normalize( $raw );
            if ( $event === false )
            {
                $skipped++;
                self::writeJson( self::eventFile( $id ), array( 'fetched' => time(), 'event' => null ) );
                continue;
            }
            $images += $this->downloadImages( $client, $event, $raw );
            self::writeJson( self::eventFile( $id ), array( 'fetched' => time(), 'event' => $event ) );
            $fetched++;
        }
        $this->log[] = sprintf( 'events: %d due, %d fetched, %d not shown (drafts or past), %d failed, %d images',
                                count( $due ), $fetched, $skipped, $failed, $images );

        // the list: every cached event of the id list that still has a coming date
        $events = array();
        $now = time();
        foreach ( $ids as $id )
        {
            $data = self::readJson( self::eventFile( $id ) );
            if ( !$data || empty( $data['event'] ) )
                continue;
            $event = $data['event'];
            $dates = array_values( array_filter( $event['dates'], function ( $d ) use ( $now ) { return $d['end'] >= $now; } ) );
            if ( !$dates )
                continue;
            $events[] = array(
                'id' => $event['id'], 'title' => $event['title'], 'sub_title' => $event['sub_title'],
                'short' => $event['short'], 'place' => $event['place'], 'location' => $event['location'],
                'categories' => $event['categories'], 'dates' => $dates, 'cancelled' => $event['cancelled'],
                'image' => isset( $event['images'][0] ) ? $event['images'][0]['file'] : '' );
        }
        $ok = self::writeJson( $this->listFile(), array( 'built' => time(), 'container' => (int)$this->container->attribute( 'node_id' ),
                                                         'events' => $events ) );
        $this->listData = null;
        $this->log[] = 'list: ' . count( $events ) . ' events with coming dates' . ( $ok ? '' : ' (NOT written)' );
        return $ok;
    }

    /**
     * @return array|false the TMV event ids of the container's locations and client (v2/legacy: TmvHelper::getIds)
     */
    protected function fetchEventIds( cjwTmvClient $client )
    {
        $base = array( 'eStateIds' => array( 20, 40 ), 'eOrderFields' => 'DATE_START-ASC', 'eStartDate' => date( 'Y-m-d' ) );
        $ids = array();
        $answered = false;
        $locations = array_keys( $this->locations() );
        if ( $locations )
        {
            $answer = $client->call( 'FindEventIds', $base + array( 'eAddrLocationIds' => $locations ) );
            if ( $answer !== false )
            {
                $answered = true;
                $ids = array_merge( $ids, self::idsOf( $answer ) );
            }
        }
        if ( $this->clientId() > 0 )
        {
            // the client fetch completes events whose venue has no location id
            $answer = $client->call( 'FindEventIds', $base + array( 'eClientId' => $this->clientId() ) );
            if ( $answer !== false )
            {
                $answered = true;
                $ids = array_merge( $ids, self::idsOf( $answer ) );
            }
        }
        // events of other places named in the container (field tmv_event_ids, a matrix of ids), as in the legacy import
        foreach ( preg_split( '/[^\d]+/', $this->containerText( (string)$this->setting( 'Import', 'ForeignEventIdsAttrIdentifier', 'tmv_event_ids' ) ) ) as $id )
            if ( (int)$id > 0 )
                $ids[] = (int)$id;
        if ( !$answered && ( $locations || $this->clientId() > 0 ) )
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

    /**
     * Turns one API event into what the pages draw (legacy: TmvHelper::importEvent).
     *
     * @param array $raw the API's Event
     * @return array|false false for an event that is not shown (a draft without the internet prefix, no coming date)
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
        $languages = array( 'de', 'en' );
        $i18n = function ( $path, $html = false ) use ( $get, $languages ) {
            $out = array();
            foreach ( $languages as $lang )
            {
                $value = $get( $path . '.' . $lang );
                if ( is_string( $value ) && trim( $value ) !== '' )
                    $out[$lang] = $html ? cjwTmvHtml::clean( $value ) : trim( html_entity_decode( strip_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
            }
            return $out;
        };

        $id = (int)$get( 'id' );
        if ( $id <= 0 )
            return false;
        $title = $i18n( 'title' );
        // drafts (state 20) are shown only when their title carries the prefix, which is then removed
        if ( (int)$get( 'entityState.key' ) === 20 )
        {
            $prefix = (string)$this->setting( 'Import', 'OnlyInternetPrefix', '' );
            if ( $prefix === '' || !isset( $title['de'] ) || strpos( $title['de'], $prefix ) === false )
                return false;
            foreach ( $title as $lang => $text )
                $title[$lang] = trim( str_replace( $prefix, '', $text ) );
        }
        if ( !$title )
            return false;

        // dates: start = date + start time, end = start + duration (minutes); cancelled dates are marked
        $cancelledIds = array();
        foreach ( (array)$get( 'cancelledEventDates' ) as $item )
            if ( is_array( $item ) && isset( $item['id'] ) )
                $cancelledIds[] = (int)$item['id'];
        $dates = array();
        $now = time();
        foreach ( (array)$get( 'eventDates' ) as $item )
        {
            if ( !is_array( $item ) || empty( $item['date'] ) )
                continue;
            $start = strtotime( substr( (string)$item['date'], 0, 10 ) . ( !empty( $item['startTime'] ) ? ' ' . $item['startTime'] : '' ) );
            if ( !$start )
                continue;
            $end = $start + 60 * max( 0, (int)( isset( $item['duration'] ) ? $item['duration'] : 0 ) );
            if ( $end < $now )
                continue;
            $dates[] = array( 'start' => $start, 'end' => $end, 'has_time' => !empty( $item['startTime'] ),
                              'cancelled' => in_array( (int)( isset( $item['id'] ) ? $item['id'] : 0 ), $cancelledIds ) );
        }
        if ( !$dates )
            return false;
        usort( $dates, function ( $a, $b ) { return $a['start'] <=> $b['start']; } );

        $categories = array();
        foreach ( (array)$get( 'categories' ) as $item )
            if ( is_array( $item ) && isset( $item['id'] ) )
                $categories[] = (int)$item['id'];

        // contact (the contributor) and location (the venue), as address lines; place = the container's name of the
        // venue's location, else its city (legacy: the venue wins over the contributor)
        $locations = $this->locations();
        $place = '';
        $contact = $this->addressLines( (array)$get( 'contributor' ), $locations, $place );
        $location = $this->addressLines( (array)$get( 'location' ), $locations, $place );
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

        $link = (string)$this->setting( 'TMV', 'LinkToTmvEvent', '' );
        return array(
            'id' => $id,
            'modified' => (int)strtotime( (string)$get( 'lastChangeTime' ) ),
            'cancelled' => (bool)$get( 'cancelled' ),
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
            'dates' => $dates,
            'images' => array(),
            'link' => $link !== '' ? $link . $id : '' );
    }

    /**
     * @param array $entry contributor or location of the API event
     * @param array $locations the container's location id => name
     * @param string $place set to the place of the entry when it has a city
     * @return array address lines (name, person, street, zip + city, phone, fax, email, homepage)
     */
    protected function addressLines( array $entry, array $locations, &$place )
    {
        $c = isset( $entry['contact1'] ) && is_array( $entry['contact1'] ) ? $entry['contact1'] : array();
        $a = isset( $c['address'] ) && is_array( $c['address'] ) ? $c['address'] : array();
        $s = function ( $array, $key ) {
            return isset( $array[$key] ) && is_scalar( $array[$key] ) ? trim( (string)$array[$key] ) : '';
        };
        $lines = array();
        if ( $s( $c, 'contactName' ) !== '' )
            $lines[] = $s( $c, 'contactName' );
        if ( $s( $c, 'lastname' ) !== '' )
        {
            $person = trim( $s( $c, 'salutation' ) . ' ' . $s( $c, 'firstname' ) . ' ' . $s( $c, 'lastname' ) );
            if ( $s( $c, 'firstname' ) !== '' || $s( $c, 'contactName' ) !== $s( $c, 'lastname' ) )
                $lines[] = $person;
        }
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
     * Downloads the event's images into the public image directory, scaled to [Images] MaxWidth.
     *
     * @return int images written
     */
    protected function downloadImages( cjwTmvClient $client, array &$event, array $raw )
    {
        $max = (int)$this->setting( 'Images', 'MaxPerEvent', 4 );
        $width = (int)$this->setting( 'Images', 'MaxWidth', 1200 );
        $count = 0;
        $media = isset( $raw['media'] ) && is_array( $raw['media'] ) ? $raw['media'] : array();
        usort( $media, function ( $a, $b ) {
            return ( isset( $a['sortingValue'] ) ? (int)$a['sortingValue'] : 0 ) <=> ( isset( $b['sortingValue'] ) ? (int)$b['sortingValue'] : 0 );
        } );
        foreach ( $media as $medium )
        {
            if ( count( $event['images'] ) >= $max )
                break;
            if ( !is_array( $medium ) || empty( $medium['deeplink'] ) || !empty( $medium['deactivated'] ) )
                continue;
            $name = (int)$event['id'] . '-' . (int)( isset( $medium['id'] ) ? $medium['id'] : count( $event['images'] ) ) . '.jpg';
            $file = self::imageDir() . '/' . $name;
            if ( !is_file( $file ) )
            {
                $bytes = $client->download( $medium['deeplink'] );
                if ( $bytes === false || !@getimagesizefromstring( $bytes ) )
                    continue;
                $bytes = self::scale( $bytes, $width );
                if ( $bytes === false || !self::writeFile( $file, $bytes ) )
                    continue;
                $count++;
            }
            $title = isset( $medium['pooledMedium']['title']['de'] ) && is_string( $medium['pooledMedium']['title']['de'] )
                ? trim( $medium['pooledMedium']['title']['de'] ) : '';
            $copyright = isset( $medium['pooledMedium']['copyright']['de'] ) && is_string( $medium['pooledMedium']['copyright']['de'] )
                ? trim( $medium['pooledMedium']['copyright']['de'] ) : '';
            $event['images'][] = array( 'file' => $name, 'title' => $title, 'copyright' => $copyright );
        }
        return $count;
    }

    /**
     * @return string|false JPEG bytes no wider than $width (the original when GD is missing and it is a JPEG)
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

    /**
     * Refreshes the TMV categories when older than [Cache] CategoriesTTL.
     *
     * @return string a log line
     */
    public static function refreshCategories( cjwTmvClient $client, $force = false )
    {
        $file = self::cacheDir() . '/categories.json';
        $ttl = (int)eZINI::instance( 'cjw_tmv.ini' )->variable( 'Cache', 'CategoriesTTL' );
        if ( !$force && is_file( $file ) && time() - filemtime( $file ) < $ttl )
            return 'categories: cached';
        $answer = $client->call( 'FindCategories' );
        if ( $answer === false || !isset( $answer['categories'] ) )
            return 'categories: API not reachable, previous list kept';
        $categories = array();
        foreach ( (array)$answer['categories'] as $item )
        {
            if ( !is_array( $item ) || !isset( $item['id'] ) )
                continue;
            $names = array();
            foreach ( array( 'i18nName', 'name', 'title' ) as $key )
            {
                if ( isset( $item[$key] ) && is_array( $item[$key] ) )
                {
                    foreach ( array( 'de', 'en' ) as $lang )
                        if ( isset( $item[$key][$lang] ) && is_string( $item[$key][$lang] ) && $item[$key][$lang] !== '' )
                            $names[$lang] = trim( $item[$key][$lang] );
                    break;
                }
                if ( isset( $item[$key] ) && is_string( $item[$key] ) )
                {
                    $names['de'] = trim( $item[$key] );
                    break;
                }
            }
            if ( $names )
                $categories[(int)$item['id']] = $names;
        }
        self::writeJson( $file, array( 'built' => time(), 'categories' => $categories ) );
        return 'categories: ' . count( $categories );
    }

    /**
     * Removes cached events (and their images) that no list has named for [Cache] UnusedMaxAge seconds.
     *
     * @param array $usedIds event ids of the current lists
     * @return int files removed
     */
    public static function removeUnused( array $usedIds )
    {
        $maxAge = (int)eZINI::instance( 'cjw_tmv.ini' )->variable( 'Cache', 'UnusedMaxAge' );
        $used = array_flip( array_map( 'intval', $usedIds ) );
        $removed = 0;
        foreach ( (array)glob( self::cacheDir() . '/events/*.json' ) as $file )
        {
            $id = (int)basename( $file, '.json' );
            if ( isset( $used[$id] ) || time() - filemtime( $file ) < $maxAge )
                continue;
            if ( @unlink( $file ) )
                $removed++;
            foreach ( (array)glob( self::imageDir() . '/' . $id . '-*.jpg' ) as $image )
                if ( @unlink( $image ) )
                    $removed++;
        }
        return $removed;
    }

    /**
     * @return array event ids of this container's current list
     */
    public function listedIds()
    {
        $list = $this->loadList();
        $ids = array();
        foreach ( $list['events'] as $event )
            $ids[] = (int)$event['id'];
        return $ids;
    }
}

?>
