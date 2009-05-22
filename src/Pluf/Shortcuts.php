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
 * Shortcut methods to perform some operations.
 *
 * Modelled on the django.shortcuts.
 */

/**
 * Get an object by id or raise a 404 error.
 *
 * @param string Model
 * @param int Id of the model to get
 * @return Object The found object.
 */
function Pluf_Shortcuts_GetObjectOr404($object, $id)
{
    $item = new $object($id);
    if ((int)$id > 0 && $item->id == $id) {
        return $item;
    }
    throw new Pluf_HTTP_Error404();
}

/**
 * Get an object by SQL or raise a 404 error.
 *
 * Usage:
 * <pre>
 * $obj = Pluf_Shortcuts_GetOneOr404('MyApp_Model',
 *                                   'path=%s AND status=%s',
 *                                    array('welcome', 1));
 * </pre>
 *
 * @param string Model
 * @param string Base SQL request
 * @param string Parameters for the base SQL
 * @return Object The found object
 */
function Pluf_Shortcuts_GetOneOr404($object, $bsql, $psql)
{
    $sql = new Pluf_SQL($bsql, $psql);
    $item = Pluf::factory($object)->getOne(array('filter' => $sql->gen()));
    if ($item != null) {
        return $item;
    }
    throw new Pluf_HTTP_Error404();
}

/**
 * Render a template file and an array as a reponse.
 *
 * If a none null request object is given, the context used will
 * automatically be a Pluf_Template_Context_Request context and thus
 * the context will be populated using the
 * 'template_context_processors' functions.
 *
 * @param string Template file name
 * @param array Associative array for the context
 * @param Pluf_HTTP_Request Request object (null)
 * @return Pluf_HTTP_Response The response with the rendered template
 */
function Pluf_Shortcuts_RenderToResponse($tplfile, $params, $request=null)
{
    $tmpl = new Pluf_Template($tplfile);
    if (is_null($request)) {
        $context = new Pluf_Template_Context($params);
    } else {
        $context = new Pluf_Template_Context_Request($request, $params);
    }
    return new Pluf_HTTP_Response($tmpl->render($context));
}


/**
 * Get a given form from a model.
 *
 * @param Object The model.
 * @param array Data to bound the form (null)
 * @param array Extra parameters (array())
 * @param string Label suffix (null)
 * @return Object Form for this model.
 */
function Pluf_Shortcuts_GetFormForModel($model, $data=null, $extra=array(), $label_suffix=null)
{
    $extra['model'] = $model;
    return new Pluf_Form_Model($data, $extra, $label_suffix);
}
