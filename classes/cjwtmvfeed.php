<?php
/**
 * The TMV events of one event container (a tmv_container node), read from the content objects that the importer
 * (cjwTmvImporter, cronjob part cjw_tmv) keeps below it: the container's settings, the filter of the request, and
 * the events at their next date (v2: Cjw\TmvBundle TmvEventsHandler::fetchEventsByFilter).
 *
 * The list comes from two SQL reads (the coming dates, the filterable fields of the events), so a list over
 * hundreds of events loads only the event nodes of the page that is drawn.
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

    /** @var array|null event node id => filter fields, loaded once */
    protected $events = null;

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


    /**
     * @return array extra TMV event ids named in the container (field tmv_event_ids, any separators)
     */
    public function foreignEventIds()
    {
        $ids = array();
        $field = (string)$this->setting( 'Import', 'ForeignEventIdsAttrIdentifier', 'tmv_event_ids' );
        foreach ( preg_split( '/[^\d]+/', $this->containerText( $field ) ) as $id )
            if ( (int)$id > 0 )
                $ids[] = (int)$id;
        return $ids;
    }

    /* ---------------------------------------------------------------------------------------------------------
     * Reading (pages)
     * ------------------------------------------------------------------------------------------------------- */

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
     * @return int the id of a class attribute (0 when the class or attribute is missing)
     */
    protected static function attributeId( $class, $identifier )
    {
        static $cache = array();
        if ( !isset( $cache["$class/$identifier"] ) )
        {
            $c = eZContentClass::fetchByIdentifier( $class );
            $a = $c ? $c->fetchAttributeByIdentifier( $identifier ) : null;
            $cache["$class/$identifier"] = $a ? (int)$a->attribute( 'id' ) : 0;
        }
        return $cache["$class/$identifier"];
    }

    /**
     * @return array event node id => title, sub_title, place, categories (ids), short (plain), dates (start, end),
     *               the visible imported events of the container with at least one coming date
     */
    protected function loadEvents()
    {
        if ( $this->events !== null )
            return $this->events;
        $this->events = array();
        $dateClass = eZContentClass::fetchByIdentifier( 'tmv_date' );
        $eventClass = eZContentClass::fetchByIdentifier( 'tmv_event' );
        if ( !$dateClass || !$eventClass )
            return $this->events;
        $db = eZDB::instance();
        $path = $db->escapeString( $this->container->attribute( 'path_string' ) );
        $now = time();
        $start = self::attributeId( 'tmv_date', 'start' );
        $end = self::attributeId( 'tmv_date', 'end' );
        $rows = $db->arrayQuery(
            'SELECT t.parent_node_id AS event_node, s.data_int AS start_ts, e.data_int AS end_ts
               FROM ezcontentobject_tree t
               JOIN ezcontentobject o ON o.id = t.contentobject_id AND o.status = 1
               JOIN ezcontentobject_attribute s ON s.contentobject_id = o.id AND s.version = o.current_version AND s.contentclassattribute_id = ' . $start . '
               JOIN ezcontentobject_attribute e ON e.contentobject_id = o.id AND e.version = o.current_version AND e.contentclassattribute_id = ' . $end . '
              WHERE o.contentclass_id = ' . (int)$dateClass->attribute( 'id' ) . "
                AND t.path_string LIKE '" . $path . "%'
                AND t.is_invisible = 0
                AND ( e.data_int >= " . $now . ' OR s.data_int >= ' . $now . ' )
              ORDER BY s.data_int, t.parent_node_id' );
        $dates = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $key = (int)$row['event_node'] . '/' . (int)$row['start_ts'];
            $dates[(int)$row['event_node']][$key] = array( 'start' => (int)$row['start_ts'], 'end' => max( (int)$row['start_ts'], (int)$row['end_ts'] ) );
        }
        if ( !$dates )
            return $this->events;

        $fields = array();
        foreach ( array( 'title', 'sub_title', 'place', 'categories', 'full_intro' ) as $identifier )
            $fields[self::attributeId( 'tmv_event', $identifier )] = $identifier;
        $rows = $db->arrayQuery(
            'SELECT t.node_id, a.contentclassattribute_id, a.language_code, a.data_text
               FROM ezcontentobject_tree t
               JOIN ezcontentobject o ON o.id = t.contentobject_id AND o.status = 1
               JOIN ezcontentobject_attribute a ON a.contentobject_id = o.id AND a.version = o.current_version
              WHERE o.contentclass_id = ' . (int)$eventClass->attribute( 'id' ) . '
                AND t.parent_node_id = ' . (int)$this->container->attribute( 'node_id' ) . '
                AND t.is_invisible = 0
                AND a.contentclassattribute_id IN (' . implode( ',', array_map( 'intval', array_keys( $fields ) ) ) . ')' );
        $locale = eZINI::instance( 'site.ini' )->variable( 'RegionalSettings', 'ContentObjectLocale' );
        $byLanguage = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
            $byLanguage[(int)$row['node_id']][$row['language_code']][$fields[(int)$row['contentclassattribute_id']]] = (string)$row['data_text'];
        foreach ( $byLanguage as $nodeId => $languages )
        {
            if ( !isset( $dates[$nodeId] ) )
                continue;
            $values = isset( $languages[$locale] ) ? $languages[$locale] : ( isset( $languages['ger-DE'] ) ? $languages['ger-DE'] : reset( $languages ) );
            $this->events[$nodeId] = array(
                'title' => isset( $values['title'] ) ? $values['title'] : '',
                'sub_title' => isset( $values['sub_title'] ) ? $values['sub_title'] : '',
                'place' => isset( $values['place'] ) ? trim( $values['place'] ) : '',
                'categories' => array_map( 'intval', array_filter( explode( '-', isset( $values['categories'] ) ? $values['categories'] : '' ), 'is_numeric' ) ),
                'short' => self::plain( isset( $values['full_intro'] ) ? $values['full_intro'] : '', 1000 ),
                'dates' => array_values( $dates[$nodeId] ) );
        }
        return $this->events;
    }

    /**
     * The container's events that match the filter, next date first.
     *
     * @param array $filter see filterParams()
     * @param int $offset
     * @param int $limit 0 = page limit of the container
     * @param array $excludeCategories category ids left out unless the filter asks for them (v2 block parameter)
     * @return array list (each: node, next_start, next_end, date_count, short_text), total, limit
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
        foreach ( $this->loadEvents() as $nodeId => $event )
        {
            if ( $filter['places'] && !in_array( $event['place'], $filter['places'], true ) )
                continue;
            if ( !$all && !array_intersect( $categories, $event['categories'] ) )
                continue;
            if ( $exclude && array_intersect( $exclude, $event['categories'] ) )
                continue;
            if ( $keyword !== '' && mb_strpos( mb_strtolower( implode( ' ', array( $event['title'], $event['sub_title'], $event['short'], $event['place'] ) ) ), $keyword ) === false )
                continue;
            $dates = array();
            foreach ( $event['dates'] as $date )
                if ( $date['end'] >= $from && $date['start'] <= $to )
                    $dates[] = $date;
            if ( !$dates || ( $filter['hide_events_with_multiple_dates'] && count( $dates ) >= 3 ) )
                continue;
            $matches[] = array( 'node_id' => $nodeId, 'next_start' => $dates[0]['start'], 'next_end' => $dates[0]['end'],
                                'date_count' => count( $dates ), 'short_text' => self::plain( $event['short'], 260 ) );
        }
        usort( $matches, function ( $a, $b ) {
            return $a['next_start'] <=> $b['next_start'] ?: $a['node_id'] <=> $b['node_id'];
        } );
        $page = array_slice( $matches, max( 0, (int)$offset ), $limit );
        $nodes = array();
        if ( $page )
        {
            foreach ( (array)eZContentObjectTreeNode::fetch( array_column( $page, 'node_id' ) ) as $node )
                if ( $node instanceof eZContentObjectTreeNode )
                    $nodes[(int)$node->attribute( 'node_id' )] = $node;
        }
        $list = array();
        foreach ( $page as $item )
        {
            if ( !isset( $nodes[$item['node_id']] ) || !$nodes[$item['node_id']]->canRead() )
                continue;
            $item['node'] = $nodes[$item['node_id']];
            $list[] = $item;
        }
        return array( 'list' => $list, 'total' => count( $matches ), 'limit' => $limit );
    }

    /**
     * @return array place names of the container's events (v2: distinct "place" values of tmv_event), sorted
     */
    public function places()
    {
        $places = array();
        foreach ( $this->loadEvents() as $event )
            if ( $event['place'] !== '' )
                $places[$event['place']] = true;
        $places = array_keys( $places );
        sort( $places, SORT_STRING | SORT_FLAG_CASE );
        return $places;
    }

    /**
     * @return array TMV category id => title of the tmv_categorie objects in the container's subtree (v2:
     *               TmvEventsHandler::fetchAllCategories), sorted by title
     */
    public function categories()
    {
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( array(
            'ClassFilterType' => 'include', 'ClassFilterArray' => array( 'tmv_categorie' ), 'LoadDataMap' => true ),
            $this->container->attribute( 'node_id' ) );
        $list = array();
        foreach ( is_array( $nodes ) ? $nodes : array() as $node )
        {
            $dataMap = $node->attribute( 'data_map' );
            $id = isset( $dataMap['id'] ) ? (int)$dataMap['id']->attribute( 'data_text' ) : 0;
            if ( $id > 0 )
                $list[$id] = isset( $dataMap['title'] ) ? (string)$dataMap['title']->attribute( 'data_text' ) : (string)$id;
        }
        asort( $list, SORT_STRING | SORT_FLAG_CASE );
        return $list;
    }
}

?>
