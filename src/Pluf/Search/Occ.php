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
 * Storage of the occurence of the words.
 */
class Pluf_Search_Occ extends Pluf_Model
{
    public $_model = 'Pluf_Search_Occ';

    function init()
    {
        $this->_a['verbose'] = __('occurence');
        $this->_a['table'] = 'pluf_search_occs';
        $this->_a['model'] = 'Pluf_Search_Occ';
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'word' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_Search_Word',
                                  'blank' => false,
                                  'verbose' => __('word'),
                                   ),
                            'model_class' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 150,
                                  'verbose' => __('model class'),
                                  ),
                            'model_id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('model id'),
                                  ),
                            'occ' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('occurences'),
                                  ),
                            'pondocc' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Float',
                                  'blank' => false,
                                  'verbose' => __('weighted occurence'),
                                  ),
                            );
        $this->_a['idx'] = array(                           
                            'model_class_id_combo_word_idx' =>
                            array(
                                  'type' => 'unique',
                                  'col' => 'model_class, model_id, word',
                                  ),
                            );

    }

    function __toString()
    {
        return $this->word;
    }
}

