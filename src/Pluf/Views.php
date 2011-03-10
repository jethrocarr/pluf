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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('Pluf_Shortcuts_GetFormForModel');
Pluf::loadFunction('Pluf_Shortcuts_GetObjectOr404');
Pluf::loadFunction('Pluf_Shortcuts_RenderToResponse');

/**
 * Some basic views that can be reused by other views.
 *
 * Most of them are not supposed to be called directly via the
 * dispatcher as they take extra parameters for basic customization.
 */
class Pluf_Views
{
    /**
     * Simple redirection view.
     *
     * @param Request Request object
     * @param array Match
     * @param string Redirection URL (not a view)
     */
    function redirectTo($request, $match, $url)
    {
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Simple content view.
     *
     * @param Request Request object
     * @param array Match
     * @param string Content of the page
     */
    function simpleContent($request, $match, $content)
    {
        return new Pluf_HTTP_Response($content);
    }

    /**
     * Log the user in.
     *
     * The login form is provided by the login_form.html template.
     * The '_redirect_after' hidden value is used to redirect the user
     * after successfull login. If the view is called with
     * _redirect_after set in the query as a GET variable it will be
     * available as $_redirect_after in the template.
     *
     * @param Request Request object
     * @param array Match
     * @param string Default redirect URL after login ('/')
     * @param array Extra context values (array()).
     * @param string Login form template ('login_form.html')
     * @return Response object
     */
    function login($request, $match, $success_url='/', $extra_context=array(),
                   $template='login_form.html')
    {
        if (!empty($request->REQUEST['_redirect_after'])) {
            $success_url = $request->REQUEST['_redirect_after'];
        }
        $error = '';
        if ($request->method == 'POST') { 
            foreach (Pluf::f('auth_backends', array('Pluf_Auth_ModelBackend'))
                     as $backend) {
                $user = call_user_func(array($backend, 'authenticate'),
                                       $request->POST);
                if ($user !== false) {
                    break;
                }
            }
            if (false === $user) {
                $error = __('The login or the password is not valid. The login and the password are case sensitive.');
            } else {
                if (!$request->session->getTestCookie()) {
                    $error = __('You need to enable the cookies in your browser to access this website.');
                } else {
                    $request->user = $user;
                    $request->session->clear();
                    $request->session->setData('login_time', gmdate('Y-m-d H:i:s'));
                    $user->last_login = gmdate('Y-m-d H:i:s');
                    $user->update();
                    $request->session->deleteTestCookie();
                    return new Pluf_HTTP_Response_Redirect($success_url);
                }
            }
        }
        // Show the login form
        $request->session->createTestCookie();
        $context = new Pluf_Template_Context_Request($request,
                           array_merge(array(
                           'page_title' => __('Sign In'),
                           '_redirect_after' => $success_url,
                           'error' => $error), $extra_context));
        $tmpl = new Pluf_Template($template);
        return new Pluf_HTTP_Response($tmpl->render($context));
    }

    /**
     * Logout the user.
     *
     * The success url is either an absolute url starting with
     * http(s):// or considered as an action.
     *
     * @param Request Request object
     * @param array Match
     * @param string Default redirect URL after login '/'
     * @return Response object
     */
    function logout($request, $match, $success_url='/')
    {
        $user_model = Pluf::f('pluf_custom_user','Pluf_User');
        $request->user = new $user_model();
        $request->session->clear();
        $request->session->setData('logout_time', gmdate('Y-m-d H:i:s'));
        if (0 !== strpos($success_url, 'http')) {
            $murl = new Pluf_HTTP_URL();
            $success_url = Pluf::f('app_base').$murl->generate($success_url);
        }
        return new Pluf_HTTP_Response_Redirect($success_url);
    }


    /**
     * Create an object (Part of the CRUD series).
     *
     * The minimal extra parameter is the model class name. The list
     * of extra parameters is:
     *
     * 'model' - Class name string, required.
     * 
     * 'extra_context' - Array of key/values to be added to the
     *                   context (array())
     * 
     * 'extra_form' - Array of key/values to be added to the
     *                   form generation (array())
     *
     * 'login_required' - Do we require login (false)
     *
     * 'template' - Template to use ('"model class"_create_form.html')
     *
     * 'post_save_redirect' - View to redirect after saving (use
     *                        getAbsoluteUrl() method of the mode)
     *
     * 'post_save_redirect_keys' - Which keys of the model to pass to
     *                             the view (array('id'))
     *
     * @param Pluf_HTTP_Request Request object
     * @param array Match
     * @param array Extra parameters
     * @return Pluf_HTTP_Response Response object (can be a redirect)
     */
    public function createObject($request, $match, $p)
    {
        $default = array(
                         'extra_context' => array(),
                         'extra_form' => array(),
                         'login_required' => false,
                         );
        $p = array_merge($default, $p);
        if (isset($p['login_required']) && true == $p['login_required']) {
            if ($request->user->isAnonymous()) {
                return new Pluf_HTTP_Response_RedirectToLogin($request);
            }
        }
        if (!isset($p['model'])) {
            throw new Exception('The model class was not provided in the parameters.');
        }
        // Set the default
        $model = $p['model'];
        $context = (isset($p['extra_context'])) ? $p['extra_context'] : array();
        $template = (isset($p['template'])) ? $p['template'] : strtolower($model).'_create_form.html';
        $post_save_keys = (isset($p['post_save_redirect_keys'])) ? $p['post_save_redirect_keys'] : array('id');


        $object = new $model();
        if ($request->method == 'POST') {
            $form = Pluf_Shortcuts_GetFormForModel($object, $request->POST, $p['extra_form']);
            if ($form->isValid()) {
                $object = $form->save();
                if (isset($p['post_save_redirect'])) {
                    $url = Pluf_HTTP_URL_urlForView($p['post_save_redirect'],
                                                    $post_save_keys);
                } elseif (in_array('getAbsoluteUrl', 
                                   get_class_methods($object))) {
                    $url = $object->getAbsoluteUrl();
                } else {
                    throw new Exception('No URL to redirect to from generic create view.');
                }
                if (!$request->user->isAnonymous()) {
                    $request->user->setMessage(sprintf(__('The %s was created successfully.'), $object->_a['verbose']));
                }
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = Pluf_Shortcuts_GetFormForModel($object, null, $p['extra_form']);
        }
        return Pluf_Shortcuts_RenderToResponse($template,
                                               array_merge($context,
                                                           array('form' => $form)),
                                               $request);
    }

    /**
     * Update an object (Part of the CRUD series).
     *
     * The minimal extra parameter is the model class name. The list
     * of extra parameters is:
     *
     * 'model' - Class name string, required.
     * 
     * 'model_id' - Id of of the current model to update
     * 
     * 'extra_context' - Array of key/values to be added to the
     *                   context (array())
     * 
     * 'extra_form' - Array of key/values to be added to the
     *                   form generation (array())
     *
     * 'login_required' - Do we require login (false)
     *
     * 'template' - Template to use ('"model class"_update_form.html')
     *
     * 'post_save_redirect' - View to redirect after saving (use
     *                        getAbsoluteUrl() method of the mode)
     *
     * 'post_save_redirect_keys' - Which keys of the model to pass to
     *                             the view (array('id'))
     *
     * @param Pluf_HTTP_Request Request object
     * @param array Match
     * @param array Extra parameters
     * @return Pluf_HTTP_Response Response object (can be a redirect)
     */
    public function updateObject($request, $match, $p)
    {
        if (isset($p['login_required']) && true == $p['login_required']) {
            if ($request->user->isAnonymous()) {
                return new Pluf_HTTP_Response_RedirectToLogin($request);
            }
        }
        if (!isset($p['model'])) {
            throw new Exception('The model class was not provided in the parameters.');
        }
        // Set the default
        $model = $p['model'];
        $model_id = $p['model_id'];
        $context = (isset($p['extra_context'])) ? $p['extra_context'] : array();
        $template = (isset($p['template'])) ? $p['template'] : strtolower($model).'_update_form.html';
        $post_save_keys = (isset($p['post_save_redirect_keys'])) ? $p['post_save_redirect_keys'] : array('id');

        $object = Pluf_Shortcuts_GetObjectOr404($model, $model_id);
        if ($request->method == 'POST') {
            $form = Pluf_Shortcuts_GetFormForModel($object, $request->POST, $p['extra_form']);
            if ($form->isValid()) {
                $object = $form->save();
                if (isset($p['post_save_redirect'])) {
                    $url = Pluf_HTTP_URL_urlForView($p['post_save_redirect'],
                                                    $post_save_keys);
                } elseif (in_array('getAbsoluteUrl', 
                                   get_class_methods($object))) {
                    $url = $object->getAbsoluteUrl();
                } else {
                    throw new Exception('No URL to redirect to from generic create view.');
                }
                if (!$request->user->isAnonymous()) {
                    $request->user->setMessage(sprintf(__('The %s was created successfully.'), $object->_a['verbose']));
                }
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = Pluf_Shortcuts_GetFormForModel($object, $object->getData(), $p['extra_form']);
        }
        return Pluf_Shortcuts_RenderToResponse($template,
                                               array_merge($context,
                                                           array('form' => $form,
                                                                 'object' => $object)),
                                               $request);
    }


    /**
     * Delete an object (Part of the CRUD series).
     *
     * The minimal extra parameter is the model class name. The list
     * of extra parameters is:
     *
     * 'model' - Class name string, required.
     *
     * 'post_delete_redirect' - View to redirect after saving, required.
     *
     * 'id' - Index in the match to fin the id of the object to delete (1)
     * 
     * 'login_required' - Do we require login (false)
     *
     * 'template' - Template to use ('"model class"_confirm_delete.html')
     *
     * 'post_delete_redirect_keys' - Which keys of the model to pass to
     *                             the view (array())
     *
     * 'extra_context' - Array of key/values to be added to the
     *                   context (array())
     * 
     * @param Pluf_HTTP_Request Request object
     * @param array Match
     * @param array Extra parameters
     * @return Pluf_HTTP_Response Response object (can be a redirect)
     */
    public function deleteObject($request, $match, $p)
    {
        if (isset($p['login_required']) && true == $p['login_required']) {
            if ($request->user->isAnonymous()) {
                return new Pluf_HTTP_Response_RedirectToLogin($request);
            }
        }
        if (!isset($p['model'])) {
            throw new Exception('The model class was not provided in the parameters.');
        }
        // Set the default
        $id = (isset($p['id'])) ? $match[$p['id']] : $match[1];
        $model = $p['model'];
        $context = (isset($p['extra_context'])) ? $p['extra_context'] : array();
        $template = (isset($p['template'])) ? $p['template'] : strtolower($model).'_confirm_delete.html';
        $post_delete_keys = (isset($p['post_delete_redirect_keys'])) ? $p['post_delete_redirect_keys'] : array();

        $object = Pluf_Shortcuts_GetObjectOr404($model, $id);
        if ($request->method == 'POST') {
            $object->delete();
            if (isset($p['post_delete_redirect'])) {
                $url = Pluf_HTTP_URL_urlForView($p['post_delete_redirect'],
                                                $post_delete_keys);
            } else {
                throw new Exception('No URL to redirect to from generic delete view.');
            }
            if (!$request->user->isAnonymous()) {
                $request->user->setMessage(sprintf(__('The %s was deleted successfully.'), $object->_a['verbose']));
            }
            return new Pluf_HTTP_Response_Redirect($url);
        } 
        return Pluf_Shortcuts_RenderToResponse($template, 
                                               array_merge($context,
                                                           array('object' => $object)),
                                               $request);
    }

}