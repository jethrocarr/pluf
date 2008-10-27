<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2006 Loic d'Anterroches and contributors.
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
 * The Todo_Item is refering to the Todo_List through a foreign key.
 */
class Todo_Item extends Pluf_Model
{
    public $_model = __CLASS__;

    /**
     * The init method is used to define your model.
     */
    function init()
    {
        /**
         * The database table to store the model. 
         * The table name will be prefixed with the prefix define
         * in the global configuration.
         */
        $this->_a['table'] = 'todo_items';

        /**
         * The name of the model in the class definition.
         */
        $this->_a['model'] = 'Todo_Item';

        /**
         * The definition of the model. Each key of the associative array
         * corresponds to a "column" and the definition of the column is
         * given in the corresponding array.
         */
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'item' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  // The verbose name is all lower case
                                  'verbose' => __('todo item'),
                                   ),
                            'completed' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Boolean',
                                  'default' => false,
                                  'verbose' => __('completed'),
                                  ),
                            'list' => 
                            array(
                                  // Here we relate the model to a Todolist
                                  // model. This is like saying that a Todoitem
                                  // belongs to a given Todolist
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'blank' => false,
                                  'model' => 'Todo_List',
                                  'verbose' => __('in list'),
                                  'help_text' => __('To easily manage your todo items, you are invited to organize your todo items in lists.'),
                                  ),
                            );
        /**
         * You can define the indexes.
         * Indexes are you to sort and find elements. Here we define
         * an index on the completed column to easily select the list
         * of completed or not completed elements.
         */
        $this->_a['idx'] = array(
                                 'completed_idx' => 
                                 array('col' => 'completed',
                                       'type' => 'normal'),
                                 );
        $this->_a['views'] = array(
                                   'todo' => 
                                   array(
                                         'where' => 'completed=false',
                                         ),
                                   );
    }

    public function __toString()
    {
        return $this->item.(($this->completed) ? ' - Done' : '');
    }
}

