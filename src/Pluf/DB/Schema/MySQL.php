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
 * Generator of the schemas corresponding to a given model.
 *
 * This class is for MySQL, you can create a class on the same
 * model for another database engine.
 */
class Pluf_DB_Schema_MySQL
{
    /**
     * Mapping of the fields.
     */
    public $mappings = array(
                             'varchar' => 'varchar(%s)',
                             'sequence' => 'mediumint(9) unsigned not null auto_increment',
                             'boolean' => 'bool not null',
                             'date' => 'date not null',
                             'datetime' => 'datetime not null',
                             'file' => 'varchar(150) not null',
                             'manytomany' => null,
                             'foreignkey' => 'mediumint(9) unsigned not null',
                             'text' => 'longtext not null',
                             'html' => 'longtext not null',
                             'time' => 'time not null',
                             'integer' => 'integer',
                             'email' => 'varchar(150) not null',
                             'password' => 'varchar(150) not null',
                             'float' => 'numeric(%s, %s)',
                             );

    public $defaults = array(
                             'varchar' => "''",
                             'sequence' => null,
                             'boolean' => 1,
                             'date' => 0,
                             'datetime' => 0,
                             'file' => "''",
                             'manytomany' => null,
                             'foreignkey' => 0,
                             'text' => "''",
                             'html' => "''",
                             'time' => 0,
                             'integer' => 0,
                             'email' => "''",
                             'password' => "''",
                             'float' => 0.0,

                             );
    private $con = null;

    function __construct($con)
    {
        $this->con = $con;
    }



    /**
     * Get the SQL to generate the tables of the given model.
     *
     * @param Object Model
     * @return array Array of SQL strings ready to execute.
     */
    function getSqlCreate($model)
    {
        $tables = array();
        $cols = $model->_a['cols'];
        $manytomany = array();
        $sql = 'CREATE TABLE `'.$this->con->pfx.$model->_a['table'].'` (';

        foreach ($cols as $col => $val) {
            $field = new $val['type']();
            if ($field->type != 'manytomany') {
                $sql .= "\n`".$col.'` ';
                $_tmp = $this->mappings[$field->type];
                if ($field->type == 'varchar') {
                    if (isset($val['size'])) {
                        $_tmp = sprintf($this->mappings['varchar'], $val['size']);
                    } else {
                        $_tmp = sprintf($this->mappings['varchar'], '150');
                    }
                }
                if ($field->type == 'float') {
                    if (!isset($val['max_digits'])) {
                        $val['max_digits'] = 32;
                    }
                    if (!isset($val['decimal_places'])) {
                        $val['decimal_places'] = 8;
                    }
                    $_tmp = sprintf($this->mappings['float'], $val['max_digits'], $val['decimal_places']);
                }
                $sql .= $_tmp;
                if (isset($val['default'])) {
                    $sql .= ' default '.$this->con->esc($val['default']);
                } elseif ($field->type != 'sequence') {
                    $sql .= ' default '.$this->defaults[$field->type];
                }
                $sql .= ',';
            } else {
                $manytomany[] = $col;
            }
        }
        $sql .= "\n".'primary key (`id`)';
        $sql .= "\n".') ENGINE=MyISAM';
        $sql .=' DEFAULT CHARSET=utf8;';
        $tables[$this->con->pfx.$model->_a['table']] = $sql;
        
        //Now for the many to many
        foreach ($manytomany as $many) {
            $omodel = new $cols[$many]['model']();
            $hay = array(strtolower($model->_a['model']), strtolower($omodel->_a['model']));
            sort($hay);
            $table = $hay[0].'_'.$hay[1].'_assoc';
            $sql = 'CREATE TABLE `'.$this->con->pfx.$table.'` (';
            $sql .= "\n".'`'.strtolower($model->_a['model']).'_id` '.$this->mappings['foreignkey'].' default 0,';
            $sql .= "\n".'`'.strtolower($omodel->_a['model']).'_id` '.$this->mappings['foreignkey'].' default 0,';
            $sql .= "\n".'primary key (`'.strtolower($model->_a['model']).'_id`, `'.strtolower($omodel->_a['model']).'_id`)';
            $sql .= "\n".') ENGINE=MyISAM';
            $sql .=' DEFAULT CHARSET=utf8;';
            $tables[$this->con->pfx.$table] = $sql;
        }
        return $tables;
    }

    /**
     * Get the SQL to generate the indexes of the given model.
     *
     * @param Object Model
     * @return array Array of SQL strings ready to execute.
     */
    function getSqlIndexes($model)
    {
        $index = array();
        foreach ($model->_a['idx'] as $idx => $val) {
            if (!isset($val['col'])) {
                $val['col'] = $idx;
            }
            $index[$this->con->pfx.$model->_a['table'].'_'.$idx] = 
                sprintf('CREATE INDEX `%s` ON `%s` (`%s`);',
                        $idx, $this->con->pfx.$model->_a['table'], $val['col']);
        }
        foreach ($model->_a['cols'] as $col => $val) {
            $field = new $val['type']();
            if ($field->type == 'foreignkey') {
                $index[$this->con->pfx.$model->_a['table'].'_'.$col.'_foreignkey'] = 
                    sprintf('CREATE INDEX `%s` ON `%s` (`%s`);',
                            $col.'_foreignkey_idx', $this->con->pfx.$model->_a['table'], $col);
            }
            if (isset($val['unique']) and $val['unique'] == true) {
                $index[$this->con->pfx.$model->_a['table'].'_'.$col.'_unique'] = 
                    sprintf('CREATE UNIQUE INDEX `%s` ON `%s` (`%s`);',
                        $col.'_unique_idx', $this->con->pfx.$model->_a['table'], $col);
            }
        }
        return $index;
    }

    /**
     * Get the SQL to drop the tables corresponding to the model.
     *
     * @param Object Model
     * @return string SQL string ready to execute.
     */
    function getSqlDelete($model)
    {
        $cols = $model->_a['cols'];
        $manytomany = array();
        $sql = 'DROP TABLE IF EXISTS `'.$this->con->pfx.$model->_a['table'].'`';

        foreach ($cols as $col => $val) {
            $field = new $val['type']();
            if ($field->type == 'manytomany') {
                $manytomany[] = $col;
            }
        }
        
        //Now for the many to many
        foreach ($manytomany as $many) {
            $omodel = new $cols[$many]['model']();
            $hay = array(strtolower($model->_a['model']), strtolower($omodel->_a['model']));
            sort($hay);
            $table = $hay[0].'_'.$hay[1].'_assoc';
            $sql .= ', `'.$this->con->pfx.$table.'`';
        }
        return array($sql);

    }
}
