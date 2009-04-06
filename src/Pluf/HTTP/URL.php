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
     * We directly get the PATH_INFO variable or return '/'
     *
     * @return string Action
     */
    public static function getAction()
    {
        if (isset($_GET['_pluf_action'])) {
            return $_GET['_pluf_action'];
        }
        return (isset($_SERVER['PATH_INFO'])) ?
            $_SERVER['PATH_INFO'] : '/';
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
    $model = '';
    $method = '';
    if (false !== strpos($view, '::')) {
        list($model, $method) = split('::', $view);
    }
    $vdef = array($model, $method, $view);
    $regbase = array('', array());
    $regbase = Pluf_HTTP_URL_find($GLOBALS['_PX_views'], $vdef, $regbase);
    if ($regbase === false) {
        throw new Exception(sprintf('Error, the view: %s has not been found.', $view));
    }
    $url = '';
    foreach ($regbase[1] as $regex) {
        $url .= Pluf_HTTP_URL_buildReverseUrl($regex, $params);
    }
    if (!defined('IN_UNIT_TESTS')) {
        $url = $regbase[0].$url;
    }
    return $url;
}


/**
 * Go in the list of views to find the matching one.
 *
 * @param array Views
 * @param array View definition array(model, method, name)
 * @param array Regex of the view up to now and base
 * @return mixed Regex of the view or false
 */
function Pluf_HTTP_URL_find($views, $vdef, $regbase)
{
    foreach ($views as $dview) {
        if (
            (isset($dview['name']) && $dview['name'] == $vdef[2])
            or
            ($dview['model'] == $vdef[0] && $dview['method'] == $vdef[1])
            ) {
            $regbase[1][] = $dview['regex'];
            if (!empty($dview['base'])) {
                $regbase[0] = $dview['base'];
            }
            return $regbase;
        }
        if (isset($dview['sub'])) {
            $regbase2 = $regbase;
            $regbase2[1][] = $dview['regex'];
            $res = Pluf_HTTP_URL_find($dview['sub'], $vdef, $regbase2);
            if ($res) {
                return $res;
            }
        }
    }
    return false;
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
    preg_match('/^#\^?([^#\$]+)/', $url, $matches);
    return $matches[1];
}
