<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume Framework is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume Framework is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Generate a ready to use URL to be used in location/redirect or forms.
 *
 * When redirecting a user, depending of the format of the url with
 * mod_rewrite or not, the parameters must all go in the GET or
 * everything but the action. This class provide a convinient way to
 * generate those url and parse the results for the dispatcher.
 */
class Pluf_HTTP_URL
{
    /**
     * Generate the URL.
     *
     * The & is encoded as &amp; in the url.
     *
     * @param string Action url
     * @param array Associative array of the parameters (array())
     * @param bool Encode the & in the url (true)
     * @return string Ready to use URL.
     */
    public static function generate($action, $params=array(), $encode=true)
    {
        if ($encode) {
            $amp = '&amp;';
        } else {
            $amp = '&';
        }
        $url = $action;
        if (count($params) > 0) {
            $url .= '?';
            $params_list = array();
            foreach ($params as $key=>$value) {
                $params_list[] = urlencode($key).'='.urlencode($value);
            }
            $url .= implode($amp, $params_list);
        }
        return $url;
    }

    /**
     * Get the action of the request.
     *
     * Depending of the format, the action is either the path_info,
     * the query string or the _px_action parameter.
     *
     * @return string Action
     */
    public static function getAction()
    {
        if (isset($_SERVER['ORIG_PATH_INFO'])) {
            return $_SERVER['ORIG_PATH_INFO'];
        }
        if (isset($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO'];
        }
        return '/';
    }
}

/**
 * Provide the full URL (without domain) to a view.
 *
 * @param string View.
 * @param array Parameters for the view (array()).
 * @param array Extra GET parameters for the view (array()).
 * @param bool Should the URL be encoded (true).
 * @return string URL.
 */
function Pluf_HTTP_URL_urlForView($view, $params=array(), 
                                  $get_params=array(), $encoded=true)
{
    $action = Pluf_HTTP_URL_reverse($view, $params);
    if (!is_array($get_params)) {
        throw new Exception('Bad call to urlForView.');
    }
    return Pluf_HTTP_URL::generate($action, $get_params, $encoded);
}

/**
 * Reverse an URL.
 *
 * @param string View in the form 'class::method' or string of the name.
 * @param array Possible parameters for the view (array()).
 * @return string URL.
 */
function Pluf_HTTP_URL_reverse($view, $params=array())
{
    $regex = null;
    $model = '';
    $method = '';
    if (false !== strpos($view, '::')) {
        list($model, $method) = split('::', $view);
    }
    foreach ($GLOBALS['_PX_views'] as $dview) {
        if (
            (isset($dview['name']) && $dview['name'] == $view)
            or
            ($dview['model'] == $model && $dview['method'] == $method)
            ) {
            $regex = $dview['regex'];
            break;
        }
    }
    if ($regex === null) {
        throw new Exception(sprintf('Error, the view: %s has not been found.', $view));
    }
    $url = Pluf_HTTP_URL_buildReverseUrl($regex, $params);
    if (isset($dview['base']) and !defined('IN_UNIT_TESTS')) {
        $url = $dview['base'].$url;
    }
    return $url;
}

/**
 * Build the reverse URL without the path base.
 *
 * Credits to Django, again...
 *
 * @param string Regex for the URL.
 * @param array Parameters
 * @return string URL filled with the parameters.
 */
function Pluf_HTTP_URL_buildReverseUrl($url_regex, $params=array())
{
    $url_regex = str_replace('\\.', '.', $url_regex);
    $url_regex = str_replace('\\-', '-', $url_regex);
    $url = $url_regex;
    $groups = '#\(([^)]+)\)#';
    $matches = array();
    preg_match_all($groups, $url_regex, $matches);
    reset($params);
    if (count($matches[0]) && count($matches[0]) == count($params)) {
        // Test the params against the pattern
        foreach ($matches[0] as $pattern) {
            $in = current($params);
            if (0 === preg_match('#'.$pattern.'#', $in)) {
                throw new Exception('Error, param: '.$in.' is not matching the pattern: '.$pattern);
            }
            next($params);
        }
        $func = create_function('$matches', 
                                'static $p = '.var_export($params, true).'; '.
                                '$a = current($p); '.
                                'next($p); '.
                                'return $a;');
        $url = preg_replace_callback($groups, $func, $url_regex);
    }
    $url = substr(substr($url, 2), 0, -2);
    if (substr($url, -1) !== '$') {
        return $url;
    }
    return substr($url, 0, -1);
}
