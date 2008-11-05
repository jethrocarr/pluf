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
 * Sort of Active Record Class
 *
 */
class Pluf_Model
{
    public $_model = __CLASS__; //set it to your model name

    /** Database connection. */
    public $_con = null;

    /** 
     * Store the attributes of the model. To minimize pollution of the
     * property space, all the attributes are stored in this array.
     *
     * Description of the keys:
     * 'table': The table in which the model is stored.
     * 'model': The name of the model.
     * 'cols': The definition of the columns.
     * 'idx': The definition of the indexes.
     * 'views': The definition of the views.
     * 'verbose': The verbose name of the model.
     */
    public $_a = array('table' => 'model',
                       'model' => 'Pluf_Model',
                       'cols' => array(),
                       'idx' => array(),
                       'views' => array(),
                       );

    /** Storage of the data.
     *
     * The object data are stored in an associative array. Each key
     * corresponds to a column and stores a Pluf_DB_Field_* variable.
     */
    protected $_data = array(); 

    /**
     * Storage cached data for methods_get
     */
    protected $_cache = array(); // We should use a global cache.
    
    /** List of the foreign keys.
     *
     * Set by the init() method from the definition of the columns.
     */
    protected $_fk = array();

    /** 
     * Methods available, this array is dynamically populated by init
     * method.
     */ 
    protected $_m = array('list' => array(), // get_*_list methods
                          'many' => array(), // many to many
                          'get' => array(), // foreign keys
                          'extra' => array(), // added by some fields
                          );

    function __construct($pk=null, $values=array())
    {
        $this->_init();
        if ((int) $pk > 0) {
            $this->get($pk); //Should not have a side effect
        }
    }


    function init()
    {
        // Define it yourself.
    }

    /**
     * Define the list of methods for the model from the available
     * model relationship.
     */
    function _init()
    {
        $this->_getConnection();
        if (isset($GLOBALS['_PX_models_init_cache'][$this->_model])) {
            $this->_cache = $GLOBALS['_PX_models_init_cache'][$this->_model]['cache'];
            $this->_m = $GLOBALS['_PX_models_init_cache'][$this->_model]['m'];
            $this->_a = $GLOBALS['_PX_models_init_cache'][$this->_model]['a'];
            $this->_fk = $GLOBALS['_PX_models_init_cache'][$this->_model]['fk'];
            $this->_data = $GLOBALS['_PX_models_init_cache'][$this->_model]['data'];
            return;
        }
        $this->init();
        foreach ($this->_a['cols'] as $col=>$val) {
            $field = new $val['type']('', $col);
            if ($field->type == 'foreignkey') {
                $this->_m['get']['get_'.strtolower($col)] = array($val['model'], $col);
                $this->_cache['fk'][$col] = 'foreignkey';
            }
            if ($field->type == 'manytomany') {
                $this->_m['list']['get_'.strtolower($col).'_list'] = $val['model'];
                $this->_m['many'][$val['model']] = 'manytomany';
            }
            foreach ($field->methods as $method) {
                $this->_m['extra'][$method[0]] = array(strtolower($col), $method[1]);
            }
            if (array_key_exists('default', $val)) {
                $this->_data[$col] = $val['default'];
            } else {
                $this->_data[$col] = '';
            }
        }
        foreach ($GLOBALS['_PX_models'] as $model=>$val) {
            if (isset($val['relate_to'])) {
                foreach ($val['relate_to'] as $related) {
                    if ($this->_a['model'] == $related) {
                        // The current model is related to $model
                        // through one or more foreign key. We load
                        // the $model to check on which fields the
                        // foreign keys are set, as it is possible in
                        // one model to have several foreign keys to
                        // the same other model.
                        if ($model != $this->_a['model']) {
                            $_m = new $model();
                            $_fkeys = $_m->getForeignKeysToModel($this->_a['model']);
                        } else {
                            $_fkeys = $this->getForeignKeysToModel($this->_a['model']);
                        }
                        foreach ($_fkeys as $_fkey=>$_fkeyval) {
                            //For each foreign key, we add the
                            //get_xx_list method that can have a
                            //custom name through the relate_name
                            //value.
                            if (isset($_fkeyval['relate_name'])) {
                                $mname = $_fkeyval['relate_name'];
                            } else {
                                $mname = strtolower($model);
                            }
                            $this->_m['list']['get_'.$mname.'_list'] = array($model, $_fkey);
                        }
                        break;
                    }
                }
            }
            if (isset($val['relate_to_many']) && 
                in_array($this->_a['model'], $val['relate_to_many'])) {
                $this->_m['list']['get_'.strtolower($model).'_list'] = $model;
                $this->_m['many'][$model] = 'manytomany';
            }
        }
        $GLOBALS['_PX_models_init_cache'][$this->_model] = array();
        $GLOBALS['_PX_models_init_cache'][$this->_model]['cache'] = $this->_cache;
        $GLOBALS['_PX_models_init_cache'][$this->_model]['m'] = $this->_m;
        $GLOBALS['_PX_models_init_cache'][$this->_model]['a'] = $this->_a;
        $GLOBALS['_PX_models_init_cache'][$this->_model]['fk'] = $this->_fk;
        $GLOBALS['_PX_models_init_cache'][$this->_model]['data'] = $this->_data;
    }

    /**
     * Get the foreign keys relating to a given model.
     *
     * @param string Model
     * @return array Foreign keys
     */
    function getForeignKeysToModel($model)
    {
        $keys = array();
        foreach ($this->_a['cols'] as $col=>$val) {
            $field = new $val['type']();
            if ($field->type == 'foreignkey' and $val['model'] == $model) {
                $keys[$col] = $val;
            }
        }
        return $keys;
    }

    /**
     * Get the raw data of the object.
     *
     * @return array Associative array of the data.
     */
    function getData()
    {
        return $this->_data;
    }

    /**
     * Set the association of a model to another in many to many.
     *
     * @param object Object to associate to the current object
     */
    function setAssoc($model)
    {
        if (!$this->delAssoc($model)) {
            return false;
        }
        $hay = array(strtolower($model->_a['model']), strtolower($this->_a['model']));
        sort($hay);
        $table = $hay[0].'_'.$hay[1].'_assoc';
        $req = 'INSERT INTO '.$this->_con->pfx.$table."\n";
        $req .= '('.$this->_con->qn(strtolower($this->_a['model']).'_id').', '
            .$this->_con->qn(strtolower($model->_a['model']).'_id').') VALUES '."\n";
        $req .= '('.$this->_toDb($this->_data['id'], 'id').', ';
        $req .= $this->_toDb($model->id, 'id').')';
        $this->_con->execute($req);
        return true;
    }

    /**
     * Set the association of a model to another in many to many.
     *
     * @param object Object to associate to the current object
     */
    function delAssoc($model)
    {

        //check if ok to make the association
        //current model has a many to many key with $model
        //$model has a many to many key with current model
        if (!isset($this->_m['many'][$model->_a['model']])
            or strlen($this->_data['id']) == 0
            or strlen($model->id) == 0) {
            return false;
        }
        $hay = array(strtolower($model->_a['model']), strtolower($this->_a['model']));
        sort($hay);
        $table = $hay[0].'_'.$hay[1].'_assoc';
        $req = 'DELETE FROM '.$this->_con->pfx.$table.' WHERE'."\n";
        $req .= $this->_con->qn(strtolower($this->_a['model']).'_id').' = '.$this->_toDb($this->_data['id'], 'id');
        $req .= ' AND '.$this->_con->qn(strtolower($model->_a['model']).'_id').' = '.$this->_toDb($model->id, 'id');
        $this->_con->execute($req);
        return true;
    }

    /**
     * Bulk association of models to the current one.
     *
     * @param string Model name
     * @param array Ids of Model name
     * @return bool Success
     */
    function batchAssoc($model_name, $ids)
    {
        $currents = $this->getRelated($model_name);
        foreach ($currents as $cur) {
            $this->delAssoc($cur);
        }
        foreach ($ids as $id) {
            $m = new $model_name($id);
            if ($m->id == $id) {
                $this->setAssoc($m);
            }
        }
        return true;
    }

    /**
     * Get a database connection.
     */
    function _getConnection()
    {
        $this->_con = &Pluf::db($this);
    }

    /**
     * Get a database connection.
     */
    function getDbConnection()
    {
        return Pluf::db($this);
    }

    /**
     * Get the table of the model.
     *
     * Avoid doing the concatenation of the prefix and the table
     * manually.
     */
    function getSqlTable()
    {
        return $this->_con->pfx.$this->_a['table'];
    }

    /**
     * Overloading of the get method.
     *
     * @param string Property to get
     */
    function __get($prop)
    {
        if (array_key_exists($prop, $this->_data)) return $this->_data[$prop];
        else try {
            return $this->__call($prop, array());
        } catch (Exception $e) {
            throw new Exception(sprintf('Cannot get property "%s".', $prop));
        }
    }

    /**
     * Overloading of the set method.
     *
     * @param string Property to set
     * @param mixed Value to set
     */
    function __set($prop, $val)
    {
        if (!is_null($val) and isset($this->_cache['fk'][$prop])) $this->_data[$prop] = $val->id;
        else $this->_data[$prop] = $val;
    }

    /**
     * Overloading of the method call.
     *
     * @param string Method
     * @param array Arguments
     */
    function __call($method, $args)
    {
        // The foreign keys of the current object.
        if (isset($this->_m['get'][$method])) {
            if (isset($this->_cache[$method])) {
                return $this->_cache[$method];
            } else {
                $this->_cache[$method] = Pluf::factory($this->_m['get'][$method][0], $this->_data[$this->_m['get'][$method][1]]);
                if ($this->_cache[$method]->id == '') $this->_cache[$method] = null;
                return $this->_cache[$method];
            }
        }
        // Many to many or foreign keys on the other objects.
        if (isset($this->_m['list'][$method])) {
            if (is_array($this->_m['list'][$method])) {
                $model = $this->_m['list'][$method][0];
            } else {
                $model = $this->_m['list'][$method];
            }
            $args = array_merge(array($model, $method), $args);
            return call_user_func_array(array($this, 'getRelated'), $args);
        }
        // Extra methods added by fields
        if (isset($this->_m['extra'][$method])) {
            $args = array_merge(array($this->_m['extra'][$method][0], $method, $this), $args);
            Pluf::loadFunction($this->_m['extra'][$method][1]);
            return call_user_func_array($this->_m['extra'][$method][1], $args);
        }
        throw new Exception(sprintf('Method "%s" not available.', $method));
    }

    /**
     * Get a given item.
     *
     * @param int Id of the item.
     * @return mixed Item or false if not found.
     */
    function get($id)
    {
        $req = 'SELECT * FROM '.$this->getSqlTable().' WHERE id='.$this->_toDb($id, 'id');
        if (false === ($rs = $this->_con->select($req))) {
            throw new Exception($this->_con->getError());
        }
        if (count($rs) == 0) {
            return false;
        }
        foreach ($this->_a['cols'] as $col => $val) {
            $field = new $val['type']();
            if ($field->type != 'manytomany' && array_key_exists($col, $rs[0])) {
                $this->_data[$col] = $this->_fromDb($rs[0][$col], $col);
            }
        }
        $this->restore();
        return $this;
    }

    /**
     * Get a list of items.
     *
     * The filter should be used only for simple filtering. If you want
     * a complex query, you should create a new view.
     * Both filter and order accept an array or a string in case of multiple
     * parameters:
     * Filter:
     *    array('col1=toto', 'col2=titi') will be used in a AND query
     *    or simply 'col1=toto'
     * Order:
     *    array('col1 ASC', 'col2 DESC') or 'col1 ASC'
     * 
     * This is modelled on the DB_Table pear module interface.
     *
     * @param array Associative array with the possible following
     *              keys:
     *    'view': The view to use
     *  'filter': The where clause to use
     *   'order': The ordering of the result set
     *   'start': The number of skipped rows in the result set
     *      'nb': The number of items to get in the result set
     *   'count': Run a count query and not a select if set to true
     * @return ArrayObject of items or through an exception if
     * database failure
     */
    function getList($p=array()) 
    {
        $default = array('view' => null, 
                         'filter' => null, 
                         'order' => null, 
                         'start' => null, 
                         'select' => null,
                         'nb' => null, 
                         'count' => false);
        $p = array_merge($default, $p);
        if (!is_null($p['view']) && !isset($this->_a['views'][$p['view']])) {
            throw new Exception(sprintf(__('The view "%s" is not defined.'), $p['view']));
        }
        $query = array(
                       'select' => $this->getSelect(),
                       'from' => $this->_a['table'],
                       'join' => '',
                       'where' => '',
                       'group' => '',
                       'having' => '',
                       'order' => '',
                       'limit' => '',
                       'props' => array(),
                       );
        if (!is_null($p['view'])) {
            $query = array_merge($query, $this->_a['views'][$p['view']]);
        }
        if (!is_null($p['select'])) {
            $query['select'] = $p['select'];
        }
        if (!is_null($p['filter'])) {
            if (is_array($p['filter'])) {
                $p['filter'] = implode(' AND ', $p['filter']);
            }
            if (strlen($query['where']) > 0) {
                $query['where'] .= ' AND ';
            }
            $query['where'] .= ' ('.$p['filter'].') ';
        }
        if (!is_null($p['order'])) {
            if (is_array($p['order'])) {
                $p['order'] = implode(', ', $p['order']);
            }
            if (strlen($query['order']) > 0 and strlen($p['order']) > 0) {
                $query['order'] .= ', ';
            }
            $query['order'] .= $p['order'];
        }
        if (!is_null($p['start']) && is_null($p['nb'])) {
            $p['nb'] = 10000000;
        }
        if (!is_null($p['start'])) {
            if ($p['start'] != 0) {
                $p['start'] = (int) $p['start'];
            }
            $p['nb'] = (int) $p['nb'];
            $query['limit'] = 'LIMIT '.$p['nb'].' OFFSET '.$p['start'];
        }
        if (!is_null($p['nb']) && is_null($p['start'])) {
            $p['nb'] = (int) $p['nb'];
            $query['limit'] = 'LIMIT '.$p['nb'];
        }
        if ($p['count'] == true) {
            if (isset($query['select_count'])) {
                $query['select'] = $query['select_count'];
            } else {
                $query['select'] = 'COUNT(*) as nb_items';
            }
            $query['order'] = '';
            $query['limit'] = '';
        }
        $req = 'SELECT '.$query['select'].' FROM '
            .$this->_con->pfx.$query['from'].' '.$query['join'];
        if (strlen($query['where'])) {
            $req .= "\n".'WHERE '.$query['where'];
        }
        if (strlen($query['group'])) {
            $req .= "\n".'GROUP BY '.$query['group'];
        }
        if (strlen($query['having'])) {
            $req .= "\n".'HAVING '.$query['having'];
        }
        if (strlen($query['order'])) {
            $req .= "\n".'ORDER BY '.$query['order'];
        }
        if (strlen($query['limit'])) {
            $req .= "\n".$query['limit'];
        }
        if (false === ($rs=$this->_con->select($req))) {
            throw new Exception($this->_con->getError());
        }
        if (count($rs) == 0) {
            return new ArrayObject();
        } 
        if ($p['count'] == true) {
            return $rs;
        }
        $res = new ArrayObject();
        foreach ($rs as $row) {
            $this->_reset();
            foreach ($this->_a['cols'] as $col => $val) {
                if (isset($row[$col])) $this->_data[$col] = $this->_fromDb($row[$col], $col);
            }
            // FIXME: The associated properties need to be converted too.
            foreach ($query['props'] as $prop => $key) {
                if (isset($row[$prop])) $this->_data[$key] = $row[$prop];
            }
            $this->restore();
            $res[] = clone($this);
        }
        return $res;
    }

    /**
     * Get the number of items.
     *
     * @see getList() for definition of the keys
     *
     * @param array with associative keys 'view' and 'filter'
     * @return int The number of items
     */
    function getCount($p=array())
    {
        $p['count'] = true;
        $count = $this->getList($p);
        if (empty($count) or count($count) == 0) { 
            return 0; 
        } else {
            return (int) $count[0]['nb_items'];
        }
    }

    /**
     * Get a list of related items.
     *
     * See the getList() method for usage of the view and filters.
     *
     * @param string Class of the related items
     * @param string Method call in a many to many related
     * @param array Parameters, see getList() for the definition of
     *              the keys
     * @return array Array of items
     */
    function getRelated($model, $method=null, $p=array())
    {
        $default = array('view' => null, 
                         'filter' => null, 
                         'order' => null, 
                         'start' => null, 
                         'nb' => null, 
                         'count' => false);
        $p = array_merge($default, $p);
        if ('' == $this->_data['id']) {
            return new ArrayObject();
        }
        $m = new $model();
        if (isset($this->_m['list'][$method]) 
            and is_array($this->_m['list'][$method])) {
            $foreignkey = $this->_m['list'][$method][1];
            if (strlen($foreignkey) == 0) {
                throw new Exception(sprintf(__('No matching foreign key found in model: %s for model %s'), $model, $this->_a['model']));
            }
            if (!is_null($p['filter'])) {
                if (is_array($p['filter'])) {
                    $p['filter'] = implode(' AND ', $p['filter']);
                }
                $p['filter'] .=  ' AND ';
            } else {
                $p['filter'] = '';
            }
            $p['filter'] .= $this->_con->qn($foreignkey).'='.$this->_toDb($this->_data['id'], 'id');
        } else {
            // Many to many: We generate a special view that is making
            // the join
            $hay = array(strtolower(Pluf::factory($model)->_a['model']), 
                         strtolower($this->_a['model']));
            sort($hay);
            $table = $hay[0].'_'.$hay[1].'_assoc';
            if (isset($m->_a['views'][$p['view']])) {
                $m->_a['views'][$p['view'].'__manytomany__'] = $m->_a['views'][$p['view']];
                if (!isset($m->_a['views'][$p['view'].'__manytomany__']['join'])) {
                    $m->_a['views'][$p['view'].'__manytomany__']['join'] = '';
                }
                if (!isset($m->_a['views'][$p['view'].'__manytomany__']['where'])) {
                    $m->_a['views'][$p['view'].'__manytomany__']['where'] = '';
                }
            } else {
                $m->_a['views']['__manytomany__'] = array('join' => '',
                                                     'where' => '');
                $p['view'] = '';
            }
            $m->_a['views'][$p['view'].'__manytomany__']['join'] .= 
                ' LEFT JOIN '.$this->_con->pfx.$table.' ON '
                .$this->_con->qn(strtolower($m->_a['model']).'_id').' = '.$this->_con->pfx.$m->_a['table'].'.id';

            $m->_a['views'][$p['view'].'__manytomany__']['where'] = $this->_con->qn(strtolower($this->_a['model']).'_id').'='.$this->_data['id'];
            $p['view'] = $p['view'].'__manytomany__';
        }
        return $m->getList($p);
    }

    /**
     * Generate the SQL select from the columns
     */
    function getSelect()
    {
        if (isset($this->_cache['getSelect'])) return $this->_cache['getSelect'];
        $select = array();
        $table = $this->getSqlTable();
        foreach ($this->_a['cols'] as $col=>$val) {
            if ($val['type'] != 'Pluf_DB_Field_Manytomany') {
                $select[] = $table.'.'.$this->_con->qn($col).' AS '.$this->_con->qn($col); 
            }
        }
        $this->_cache['getSelect'] = implode(', ', $select);
        return $this->_cache['getSelect'];
    }

    /**
     * Update the model into the database.
     *
     * If no where clause is provided, the index definition is used to
     * find the sequence. These are used to limit the update
     * to the current model.
     *
     * @param string Where clause to update specific items. ('')
     * @return bool Success
     */
    function update($where='')
    {
        $this->preSave();
        $req = 'UPDATE '.$this->getSqlTable().' SET'."\n";
        $fields = array();
        $assoc = array();
        foreach ($this->_a['cols'] as $col=>$val) {
            $field = new $val['type']();
            if ($col == 'id') {
                continue;
            } elseif ($field->type == 'manytomany') {
                if (is_array($this->$col)) {
                    $assoc[$val['model']] = $this->$col;
                }
                continue;
            }
            $fields[] = $this->_con->qn($col).' = '.$this->_toDb($this->$col, $col);
        }
        $req .= implode(','."\n", $fields);
        if (strlen($where) > 0) {
            $req .= ' WHERE '.$where;
        } else {
            $req .= ' WHERE id = '.$this->_toDb($this->_data['id'], 'id');
        }
        $this->_con->execute($req);
        if (false === $this->get($this->_data['id'])) {
            return false;
        }
        foreach ($assoc as $model=>$ids) {
            $this->batchAssoc($model, $ids);
        }
        $this->postSave();
        return true;
    }

    /**
     * Create the model into the database.
     * 
     * @return bool Success
     */
    function create($force_id=false)
    {
        $this->preSave(true);
        $req = 'INSERT INTO '.$this->getSqlTable()."\n";
        $icols = array();
        $ivals = array();
        $assoc = array();
        foreach ($this->_a['cols'] as $col=>$val) {
            $field = new $val['type']();
            if ($col == 'id' and !$force_id) {
                continue;
            } elseif ($field->type == 'manytomany') {
                // If is a defined array, we need to associate.
                if (is_array($this->$col)) {
                    $assoc[$val['model']] = $this->$col;
                }
                continue;
            }
            $icols[] = $this->_con->qn($col);
            $ivals[] = $this->_toDb($this->$col, $col);
        }
        $req .= '('.implode(', ', $icols).') VALUES ';
        $req .= '('.implode(','."\n", $ivals).')';
        $this->_con->execute($req);
        if (false === ($id=$this->_con->getLastID())) {
            throw new Exception($this->_con->getError());
        }
        $this->_data['id'] = $id;
        foreach ($assoc as $model=>$ids) {
            $this->batchAssoc($model, $ids);
        }
        $this->postSave(true);
        return true;
    }

    /**
     * Get models affected by delete.
     *
     * @return array Models deleted if deleting current model.
     */
    function getDeleteSideEffect()
    {
        $affected = array();
        foreach ($this->_m['list'] as $method=>$details) {
            if (is_array($details)) {
                // foreignkey
                $related = $this->$method();
                $affected = array_merge($affected, (array) $related);
                foreach ($related as $rel) {
                    if ($details[0] == $this->_a['model']
                        and $rel->id == $this->_data['id']) {
                        continue; // $rel == $this
                    }
                    $affected = array_merge($affected, (array) $rel->getDeleteSideEffect());
                }
            }
        }
        return Pluf_Model_RemoveDuplicates($affected);
    }

    /**
     * Delete the current model from the database.
     *
     * If another model link to the current model through a foreign
     * key, find it and delete it. If this model is linked to other
     * through a many to many, delete the association.
     *
     * FIXME: No real test of circular references. It can break.
     */
    function delete()
    {
        if (false === $this->get($this->_data['id'])) {
            return false;
        }
        $this->preDelete();
        // Drop the row level permissions if we are using them
        if (Pluf::f('pluf_use_rowpermission', false)) {
            $_rpt = Pluf::factory('Pluf_RowPermission')->getSqlTable();
            $sql = new Pluf_SQL('model_class=%s AND model_id=%s',
                                array($this->_a['model'], $this->_data['id']));
            $this->_con->execute('DELETE FROM '.$_rpt.' WHERE '.$sql->gen());
        }
        // Find the models linking to the current one through a foreign key.
        foreach ($this->_m['list'] as $method=>$details) {
            if (is_array($details)) {
                // foreignkey
                $related = $this->$method();
                foreach ($related as $rel) {
                    if ($details[0] == $this->_a['model']
                        and $rel->id == $this->_data['id']) {
                        continue; // $rel == $this
                    }
                    // We do not really control if it can be deleted
                    // as we can find many times the same to delete.
                    $rel->delete();
                }
            } else {
                // manytomany
                $related = $this->$method();
                foreach ($related as $rel) {
                    $this->delAssoc($rel);
                }
            }
        }
        $req = 'DELETE FROM '.$this->getSqlTable().' WHERE id = '.$this->_toDb($this->_data['id'], 'id');
        $this->_con->execute($req);
        $this->_reset();
        return true;
    }

    /**
     * Reset the fields to default values.
     */
    function _reset()
    {
        foreach ($this->_a['cols'] as $col => $val) {
            if (isset($val['default'])) {
                $this->_data[$col] = $val['default'];
            } elseif (isset($val['is_null'])) {
                $this->_data[$col] = null;
            } else {
                 $this->_data[$col] = '';
            }
        }
    }


    /**
     * Represents the model in auto generated lists.
     * 
     * You need to overwrite this method to have a nice display of
     * your objects in the select boxes, logs.
     */
    function __toString()
    {
        return $this->_a['model'].'('.$this->_data['id'].')';
    }


    /**
     * Hook run just after loading a model from the database.
     *
     * Just overwrite it into your model to perform custom actions.
     */
    function restore()
    {
    }

    /**
     * Hook run just before saving a model in the database.
     *
     * Just overwrite it into your model to perform custom actions.
     *
     * @param bool Create.
     */
    function preSave($create=false)
    {
    }

    function postSave($create=false)
    {
    }

    /**
     * Hook run just before deleting a model from the database.
     *
     * Just overwrite it into your model to perform custom actions.
     */
    function preDelete()
    {
    }

    /**
     * Set the values from form data.
     */
    function setFromFormData($cleaned_values)
    {
        foreach ($cleaned_values as $key=>$val) {
            $this->_data[$key] = $val;
        }
    }

    /**
     * Set a view.
     *
     * @param string Name of the view.
     * @param array Definition of the view.
     */
    function setView($view, $def)
    {
        $this->_a['views'][$view] = $def;
    }

    /**
     * Prepare the value to be put in the DB.
     *
     * @param mixed Value.
     * @param string Column name.
     * @return string SQL ready string.
     */
    function _toDb($val, $col)
    {
        $m = $this->_con->type_cast[$this->_a['cols'][$col]['type']][1];
        return $m($val, $this->_con);
    }

    /**
     * Get the value from the DB.
     *
     * @param mixed Value.
     * @param string Column name.
     * @return mixed Value.
     */
    function _fromDb($val, $col)
    {
        $m = $this->_con->type_cast[$this->_a['cols'][$col]['type']][0];
        return $m($val);
    }

    /**
     * Display value.
     *
     * When you have a list of choices for a field and you want to get
     * the display value of the current stored value.
     *
     * @param string Field to display the value.
     * @return mixed Display value, if not available default to the value.
     */
    function displayVal($col)
    {
        if (!isset($this->_a['cols'][$col]['choices'])) {
            return $this->_data[$col]; // will on purposed failed if not set
        }
        $val = array_search($this->_data[$col], $this->_a['cols'][$col]['choices']);
        if ($val !== false) {
            return $val;
        }
        return $this->_data[$col];
    }
}


/**
 * Check if a model is already in an array of models.
 *
 * It is not possible to override the == function in PHP to directly
 * use in_array.
 *
 * @param Pluf_Model The model to test
 * @param Array The models
 * @return bool
 */
function Pluf_Model_InArray($model, $array) 
{
    if ($model->id == '') {
        return false;
    }
    foreach ($array as $modelin) {
        if ($modelin->_a['model'] == $model->_a['model']
            and $modelin->id == $model->id) {
            return true;
        }
    }
    return false;
}

/**
 * Return a list of unique models.
 *
 * @param array Models with duplicates
 * @return array Models with duplicates.
 */
function Pluf_Model_RemoveDuplicates($array)
{
    $res = array();
    foreach ($array as $model) {
        if (!Pluf_Model_InArray($model, $res)) {
            $res[] = $model;
        }
    }
    return $res;
}

