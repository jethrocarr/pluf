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

class Pluf_Dispatcher
{
    /**
     * The unique method to call.
     *
     * @param string Query string ('')
     */
    public static function dispatch($query='')
    {
        try {
            $query = preg_replace('#^(/)+#', '/', '/'.$query);
            $req = new Pluf_HTTP_Request($query);
            $middleware = array();
            foreach (Pluf::f('middleware_classes', array()) as $mw) {
                $middleware[] = new $mw();
            }
            $skip = false;
            foreach ($middleware as $mw) {
                if (method_exists($mw, 'process_request')) {
                    $response = $mw->process_request($req);
                    if ($response !== false) {
                        // $response is a response
                        if (Pluf::f('pluf_runtime_header', false)) {
                            $response->headers['X-Perf-Runtime'] = sprintf('%.5f', (microtime(true) - $GLOBALS['_PX_starttime']));
                        }
                        $response->render($req->method != 'HEAD' and !defined('IN_UNIT_TESTS'));
                        $skip = true;
                        break;
                    }    
                }
            }
            if ($skip === false) {   
                $response = self::match($req);
                if (!empty($req->response_vary_on)) {
                    $response->headers['Vary'] = $req->response_vary_on;
                }
                $middleware = array_reverse($middleware);
                foreach ($middleware as $mw) {
                    if (method_exists($mw, 'process_response')) {
                        $response = $mw->process_response($req, $response);
                    }    
                }
                if (Pluf::f('pluf_runtime_header', false)) {
                    $response->headers['X-Perf-Runtime'] = sprintf('%.5f', (microtime(true) - $GLOBALS['_PX_starttime']));
                }
                $response->render($req->method != 'HEAD' and !defined('IN_UNIT_TESTS'));
            }
        } catch (Exception $e) {
            if (Pluf::f('debug', false) == true) {
                $response = new Pluf_HTTP_Response_ServerErrorDebug($e);
            } else {
                $response = new Pluf_HTTP_Response_ServerError($e);
            }
            $response->render($req->method != 'HEAD' and !defined('IN_UNIT_TESTS'));
        }
        /**
         * [signal]
         *
         * Pluf_Dispatcher::postDispatch
         *
         * [sender]
         *
         * Pluf_Dispatcher
         *
         * [description]
         *
         * This signal is sent after the rendering of a request. This
         * means you cannot affect the response but you can use this
         * hook to do some cleaning.
         *
         * [parameters]
         *
         * array('request' => $request,
         *       'response' => $response)
         *
         */
        $params = array('request' => $req,
                        'response' => $response);
        Pluf_Signal::send('Pluf_Dispatcher::postDispatch',
                          'Pluf_Dispatcher', $params);
        return array($req, $response);
    }

    /**
     * Match a query against the actions controllers.
     *
     * @param Pluf_HTTP_Request Request object
     * @return Pluf_HTTP_Response Response object
     */
    public static function match($req, $firstpass=true)
    {
        // Order the controllers by priority
        foreach ($GLOBALS['_PX_views'] as $key => $control) {
            $priority[$key] = $control['priority'];
        }
        array_multisort($priority, SORT_ASC, $GLOBALS['_PX_views']);
        try {
            foreach ($GLOBALS['_PX_views'] as $key => $ctl) {
                $match = array();
                if (preg_match($ctl['regex'], $req->query, $match)) {
                    $req->view = $ctl;
                    $m = new $ctl['model']();
                    if (isset($m->{$ctl['method'].'_precond'})) {
                        // Here we have preconditions to respects. If
                        // the "answer" is true, then ok go ahead, if
                        // not then it a response so return it or an
                        // exception so let it go.
                        $preconds = $m->{$ctl['method'].'_precond'};
                        if (!is_array($preconds)) {
                            $preconds = array($preconds);
                        }
                        foreach ($preconds as $precond) {
                            if (!is_array($precond)) {
                                $res = call_user_func_array(
                                              explode('::', $precond), 
                                              array(&$req)
                                                            );
                            } else {
                                $res = call_user_func_array(
                                              explode('::', $precond[0]), 
                                              array_merge(array(&$req), 
                                                          array_slice($precond, 1))
                                                            );
                            }
                            if ($res !== true) {
                                return $res;
                            }
                        } 
                    }
                    if (!isset($ctl['params'])) {
                        return $m->$ctl['method']($req, $match);
                    } else {
                        return $m->$ctl['method']($req, $match, $ctl['params']);
                    }
                }
            }
        } catch (Pluf_HTTP_Error404 $e) {
            // Need to add a 404 error handler
            // something like Pluf::f('404_handler', 'class::method')
        }
        if ($firstpass and substr($req->query, -1) != '/') {
            $req->query .= '/';
            return self::match($req, false);
        }
        return new Pluf_HTTP_Response_NotFound($req);
    }

    /**
     * Load the controllers.
     *
     * @param string File including the views.
     * @param string Possible prefix to add to the views.
     * @return bool Success.
     */
    public static function loadControllers($file, $prefix='')
    {
        if (file_exists($file)) {
            if ($prefix == '') {
                $GLOBALS['_PX_views'] = include $file;
            } else {
                $GLOBALS['_PX_views'] = Pluf_Dispatcher::addPrefixToViewFile($prefix, $file);
            }
            return true;
        }
        return false;
	}


    /**
     * Register an action controller.
     *
     * - The class must provide a "standalone" action method
     * class::actionmethod($request, $match)
     * - The priority is to order the controller matches. 
     * 5: Default, if the controller provides some content
     * 1: If the controller provides a control before, without providing
     * content, note that in this case the return code must be a redirection.
     * 8: If the controller is providing a catch all case to replace the
     * default 404 error page.
     *
     * @param string Class name providing the action controller
     * @param string The method of the plugin to be called
     * @param string Regex to match on the query string
     * @param int Priority (5)
     * @return void
     */
    public static function registerController($model, $method, $regex, $priority=5)
    {
        if (!isset($GLOBALS['_PX_views'])) {
            $GLOBALS['_PX_views'] = array();
        }
        $GLOBALS['_PX_views'][] = array('model' => $model,
                                        'regex' => $regex,
                                        'priority' => $priority,
                                        'method' => $method);
    }

    /**
     * Add the controllers of an application with a given prefix.
     *
     * Suppose you have a new app you want to use within another
     * existing application, you may need to change the base URL not
     * to conflict with the existing one. For example you want to have
     * domain.com/forum-a/ and domain.com/forum-b/ to use 2 forums at
     * the same time. 
     *
     * This method do that, it takes a typical "view" file and rewrite
     * the regex to append the prefix. Note that you should use the
     * 'url' tag in the template and use Pluf_HTTP_URL_reverse in the
     * views to not hardcode the urls or this will not work.
     *
     * @param string Prefix, for example '/alternate'.
     * @param string File with the views.
     * @return array Prefixed views.
     */
    static public function addPrefixToViewFile($prefix, $file)
    {
        if (file_exists($file)) {
            $views = include $file;
        } else {
            throw new Exception('View file not found: '.$file);
        }
        return Pluf_Dispatcher::addPrefixToViews($prefix, $views);
    }

    /**
     * Add a prefix to an array of views. 
     *
     * You can use it for example to not hardcode that in your CMS the
     * blog is located as /blog but is configured in the configuration
     * of the CMS, that way in French this could be /carnet.
     *
     * @param string Prefix, for example '/alternate'.
     * @param array Array of the views.
     * @return array Prefixed views.
     */
    static public function addPrefixToViews($prefix, $views)
    {
        $res = array();
        foreach ($views as $view) {
            $view['regex'] = '#^'.$prefix.substr($view['regex'], 2);
            $res[] = $view;
        }
        return $res;
    }
}

