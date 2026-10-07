<?php
/**
 * The client of the TMV event database (Tourismusverband Mecklenburg-Vorpommern, infomax imxplatform JSON API).
 *
 * One request per call, with the connect and total timeouts of cjw_tmv.ini [TMV]. A failure (no account, network,
 * HTTP status, broken JSON) is logged and answered with false; it never throws, so a caller can fall back to its
 * cached copy. The account is never written to a log or an exception.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvClient
{
    /** @var string the request address without the method, with the account */
    protected $base = '';

    /** @var int */
    protected $connectTimeout = 5;

    /** @var int */
    protected $timeout = 20;

    /** @var string the last error, without the account */
    public $lastError = '';

    public function __construct()
    {
        $ini = eZINI::instance( 'cjw_tmv.ini' );
        $host = trim( (string)$ini->variable( 'TMV', 'Host' ) );
        $user = $ini->hasVariable( 'TMV', 'User' ) ? trim( (string)$ini->variable( 'TMV', 'User' ) ) : '';
        $password = $ini->hasVariable( 'TMV', 'Password' ) ? (string)$ini->variable( 'TMV', 'Password' ) : '';
        if ( $host !== '' && $user !== '' )
        {
            $this->base = $host . ( strpos( $host, '?' ) === false ? '?' : '&' )
                . 'user=' . rawurlencode( $user ) . '&password=' . rawurlencode( $password );
        }
        if ( $ini->hasVariable( 'TMV', 'ConnectTimeout' ) )
            $this->connectTimeout = max( 1, (int)$ini->variable( 'TMV', 'ConnectTimeout' ) );
        if ( $ini->hasVariable( 'TMV', 'Timeout' ) )
            $this->timeout = max( 1, (int)$ini->variable( 'TMV', 'Timeout' ) );
    }

    /**
     * @return bool whether an account is configured (settings/override/cjw_tmv.ini.append.php)
     */
    public function isConfigured()
    {
        return $this->base !== '';
    }

    /**
     * Calls one API method.
     *
     * @param string $method e.g. FindEventIds, FindEvent, FindCategories
     * @param array $params name => value or name => list of values (repeated parameter)
     * @return array|false the decoded answer
     */
    public function call( $method, array $params = array() )
    {
        $this->lastError = '';
        if ( !$this->isConfigured() )
        {
            $this->lastError = 'no TMV account configured';
            return false;
        }
        if ( !function_exists( 'curl_init' ) )
        {
            $this->lastError = 'the curl extension is missing';
            return false;
        }
        $query = '&method=' . rawurlencode( $method );
        foreach ( $params as $name => $value )
        {
            foreach ( (array)$value as $item )
                $query .= '&' . rawurlencode( $name ) . '=' . rawurlencode( (string)$item );
        }
        $ch = curl_init( $this->base . $query );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Exponential cjw_tmv',
            CURLOPT_HTTPHEADER => array( 'Accept: application/json' ) ) );
        $body = curl_exec( $ch );
        $status = (int)curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_error( $ch );
        if ( PHP_VERSION_ID < 80000 ) curl_close( $ch ); // no effect since PHP 8.0, deprecated in 8.5
        if ( $body === false || $status !== 200 )
        {
            $this->lastError = $method . ': HTTP ' . $status . ( $error !== '' ? ' ' . $error : '' );
            eZDebug::writeWarning( $this->lastError, __METHOD__ );
            return false;
        }
        $data = json_decode( $body, true );
        if ( !is_array( $data ) )
        {
            $this->lastError = $method . ': the answer is not JSON';
            eZDebug::writeWarning( $this->lastError, __METHOD__ );
            return false;
        }
        return $data;
    }

    /**
     * Downloads a file (an event image) with the same timeouts.
     *
     * @param string $url an https or http address
     * @return string|false the bytes
     */
    public function download( $url )
    {
        if ( !preg_match( '#^https?://#i', (string)$url ) || !function_exists( 'curl_init' ) )
            return false;
        $ch = curl_init( $url );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Exponential cjw_tmv' ) );
        $body = curl_exec( $ch );
        $status = (int)curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        if ( PHP_VERSION_ID < 80000 ) curl_close( $ch ); // no effect since PHP 8.0, deprecated in 8.5
        return ( $body !== false && $status === 200 && $body !== '' ) ? $body : false;
    }
}

?>
