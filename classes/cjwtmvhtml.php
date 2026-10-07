<?php
/**
 * Cleans the HTML of a TMV event text before it is cached and drawn: only paragraphs, line breaks, emphasis,
 * lists, small headings and links (http, https, mailto) stay; every attribute but a link's href goes; scripts,
 * styles and embedded frames are dropped with their content; other tags give up their text only.
 * Empty paragraphs (the "&nbsp;" ones of the editors) are removed, as the legacy import did.
 *
 * @copyright Copyright (C) 2007 - 2026 CJW Network, JAC Systeme GmbH and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package cjw_tmv
 */
class cjwTmvHtml
{
    const ALLOWED = array( 'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a', 'h3', 'h4' );
    const DROPPED = array( 'script', 'style', 'iframe', 'object', 'embed', 'form', 'noscript', 'template', 'svg', 'math' );

    /**
     * @param string $html
     * @return string clean HTML ('' for no text)
     */
    public static function clean( $html )
    {
        $html = trim( (string)$html );
        if ( $html === '' )
            return '';
        // plain text with line breaks: paragraphs
        if ( strpos( $html, '<' ) === false )
        {
            $paragraphs = preg_split( '/\R{2,}/', htmlspecialchars( html_entity_decode( $html, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES, 'UTF-8' ) );
            return '<p>' . implode( '</p><p>', array_map( 'nl2br', array_map( 'trim', $paragraphs ) ) ) . '</p>';
        }
        $html = preg_replace( '/\p{Zs}/u', ' ', $html );
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $doc->loadHTML( '<?xml encoding="utf-8"?><div id="cjw-tmv-root">' . $html . '</div>', LIBXML_NONET );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $root = $doc->getElementById( 'cjw-tmv-root' );
        if ( !$root )
            return '';
        $out = '';
        foreach ( $root->childNodes as $child )
            $out .= self::node( $child );
        $out = preg_replace( '#<p>(\s|<br />)*</p>#', '', $out );
        $out = preg_replace( '#(<br />\s*){3,}#', '<br /><br />', $out );
        return trim( $out );
    }

    protected static function node( DOMNode $node )
    {
        if ( $node instanceof DOMText )
            return htmlspecialchars( $node->nodeValue, ENT_QUOTES, 'UTF-8' );
        if ( !$node instanceof DOMElement )
            return '';
        $tag = strtolower( $node->nodeName );
        if ( in_array( $tag, self::DROPPED, true ) )
            return '';
        $inner = '';
        foreach ( $node->childNodes as $child )
            $inner .= self::node( $child );
        if ( $tag === 'div' )
            return ( $inner === '' || preg_match( '#<(p|ul|ol|h3|h4)>#', $inner ) ) ? $inner : '<p>' . $inner . '</p>';
        if ( !in_array( $tag, self::ALLOWED, true ) )
            return $inner;
        if ( $tag === 'br' )
            return '<br />';
        if ( $tag === 'a' )
        {
            $href = trim( $node->getAttribute( 'href' ) );
            if ( !preg_match( '#^(https?://|mailto:)#i', $href ) )
                return $inner;
            return '<a href="' . htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ) . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
        }
        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    }
}

?>
