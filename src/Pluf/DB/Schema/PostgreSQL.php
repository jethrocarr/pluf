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
 * This class is for PostgreSQL, you can create a class on the same
 * model for another database engine.
 */
class Pluf_DB_Schema_PostgreSQL

{
    /**
     * Mapping of the fields.
     */
    public $mappings = array(
                             'varchar' => 'character varying',
                             'sequence' => 'serial',
                             'boolean' => 'boolean',
                             'date' => 'date',
                             'datetime' => 'timestamp',
                             'file' => 'character varying',
                             'manytomany' => null,
                             'foreignkey' => 'integer',
                             'text' => 'text',
                             'html' => 'text',
                             'time' => 'time',
                             'integer' => 'integer',
                             'email' => 'character varying',
                             'password' => 'character varying',
                             'float' => 'real',
                             'blob' => 'bytea',
                             );

    public $defaults = array(
                             'varchar' => "''",
                             'sequence' => null,
                             'boolean' => 'FALSE',
                             'date' => "'0001-01-01'",
                             'datetime' => "'0001-01-01 00:00:00'",
                             'file' => "''",
                             'manytomany' => null,
                             'foreignkey' => 0,
                             'text' => "''",
                             'html' => "''",
                             'time' => "'00:00:00'",
                             'integer' => 0,
                             'email' => "''",
                             'password' => "''",
                             'float' => 0.0,
                             'blob' => "''",

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
        $query = 'CREATE TABLE '.$this->con->pfx.$model->_a['table'].' (';
        $sql_col = array();
        foreach ($cols as $col => $val) {
            $field = new $val['type']();
            if ($field->type != 'manytomany') {
                $sql = $this->con->qn($col).' ';
                $sql .= $this->mappings[$field->type];
                if (empty($val['is_null'])) {
                    $sql .= ' NOT NULL';
                }
                if (isset($val['default'])) {
                    $sql .= ' default ';
                    $sql .= $model->_toDb($val['default'], $col);
                } elseif ($field->type != 'sequence') {
                    $sql .= ' default '.$this->defaults[$field->type];
                }
                $sql_col[] = $sql;
            } else {
                $manytomany[] = $col;
            }
        }
        $sql_col[] = 'CONSTRAINT '.$this->con->pfx.$model->_a['table'].'_pkey PRIMARY KEY (id)';
        $query = $query."\n".implode(",\n", $sql_col)."\n".');';
        $tables[$this->con->pfx.$model->_a['table']] = $query;
        // Now for the many to many
        // FIXME add index on the second column
        foreach ($manytomany as $many) {
            $omodel = new $cols[$many]['model']();
            $hay = array(strtolower($model->_a['model']), strtolower($omodel->_a['model']));
            sort($hay);
            $table = $hay[0].'_'.$hay[1].'_assoc';
            $sql = 'CREATE TABLE '.$this->con->pfx.$table.' (';
            $sql .= "\n".strtolower($model->_a['model']).'_id '.$this->mappings['foreignkey'].' default 0,';
            $sql .= "\n".strtolower($omodel->_a['model']).'_id '.$this->mappings['foreignkey'].' default 0,';
            $sql .= "\n".'CONSTRAINT '.$this->getShortenedIdentifierName($this->con->pfx.$table.'_pkey').' PRIMARY KEY ('.strtolower($model->_a['model']).'_id, '.strtolower($omodel->_a['model']).'_id)';
            $sql .= "\n".');';
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
            if ($val['type'] == 'unique') {
                $unique = 'UNIQUE ';
            } else {
                $unique = '';
            }

            $index[$this->con->pfx.$model->_a['table'].'_'.$idx] =
                sprintf('CREATE '.$unique.'INDEX %s ON %s (%s);',
                        $this->con->pfx.$model->_a['table'].'_'.$idx,
                        $this->con->pfx.$model->_a['table'],
                        Pluf_DB_Schema::quoteColumn($val['col'], $this->con)
                        );
        }
        foreach ($model->_a['cols'] as $col => $val) {
            $field = new $val['type']();
            if (isset($val['unique']) and $val['unique'] == true) {
                $index[$this->con->pfx.$model->_a['table'].'_'.$col.'_unique'] =
                    sprintf('CREATE UNIQUE INDEX %s ON %s (%s);',
                            $this->con->pfx.$model->_a['table'].'_'.$col.'_unique_idx',
                            $this->con->pfx.$model->_a['table'],
                            Pluf_DB_Schema::quoteColumn($col, $this->con)
                            );
            }
        }
        return $index;
    }

    /**
     * All identifiers in Postgres must not exceed 64 characters in length.
     *
     * @param string
     * @return string
     */
    function getShortenedIdentifierName($name)
    {
        if (strlen($name) <= 64) {
            return $name;
        }
        return substr($name, 0, 55).'_'.substr(md5($name), 0, 8);
    }

    /**
     * Get the SQL to create the constraints for the given model
     *
     * @param Object Model
     * @return array Array of SQL strings ready to execute.
     */
    function getSqlCreateConstraints($model)
    {
        $table = $this->con->pfx.$model->_a['table'];
        $constraints = array();
        $alter_tbl = 'ALTER TABLE '.$table;
        $cols = $model->_a['cols'];
        $manytomany = array();

        foreach ($cols as $col => $val) {
            $field = new $val['type']();
            // remember these for later
            if ($field->type == 'manytomany') {
                $manytomany[] = $col;
            }
            if ($field->type == 'foreignkey') {
                // Add the foreignkey constraints
                $referto = new $val['model']();
                $constraints[] = $alter_tbl.' ADD CONSTRAINT '.$this->getShortenedIdentifierName($table.'_'.$col.'_fkey').'
                    FOREIGN KEY ('.$this->con->qn($col).')
                    REFERENCES '.$this->con->pfx.$referto->_a['table'].' (id) MATCH SIMPLE
                    ON UPDATE NO ACTION ON DELETE NO ACTION';
            }
        }

        // Now for the many to many
        foreach ($manytomany as $many) {
            $omodel = new $cols[$many]['model']();
            $hay = array(strtolower($model->_a['model']), strtolower($omodel->_a['model']));
            sort($hay);
            $table = $this->con->pfx.$hay[0].'_'.$hay[1].'_assoc';
            $alter_tbl = 'ALTER TABLE '.$table;
            $constraints[] = $alter_tbl.' ADD CONSTRAINT '.$this->getShortenedIdentifierName($table.'_fkey1').'
                FOREIGN KEY ('.strtolower($model->_a['model']).'_id)
                REFERENCES '.$this->con->pfx.$model->_a['table'].' (id) MATCH SIMPLE
                ON UPDATE NO ACTION ON DELETE NO ACTION';
            $constraints[] = $alter_tbl.' ADD CONSTRAINT '.$this->getShortenedIdentifierName($table.'_fkey2').'
                FOREIGN KEY ('.strtolower($omodel->_a['model']).'_id)
                REFERENCES '.$this->con->pfx.$omodel->_a['table'].' (id) MATCH SIMPLE
                ON UPDATE NO ACTION ON DELETE NO ACTION';
        }
        return $constraints;
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
        $sql = array();
        $sql[] = 'DROP TABLE IF EXISTS '.$this->con->pfx.$model->_a['table'].' CASCADE';
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
            $sql[] = 'DROP TABLE IF EXISTS '.$this->con->pfx.$table.' CASCADE';
        }
        return $sql;
    }

    /**
     * Get the SQL to drop the constraints for the given model
     *
     * @param Object Model
     * @return array Array of SQL strings ready to execute.
     */
    function getSqlDeleteConstraints($model)
    {
        $table = $this->con->pfx.$model->_a['table'];
        $constraints = array();
        $alter_tbl = 'ALTER TABLE '.$table;
        $cols = $model->_a['cols'];
        $manytomany = array();

        foreach ($cols as $col => $val) {
            $field = new $val['type']();
            // remember these for later
            if ($field->type == 'manytomany') {
                $manytomany[] = $col;
            }
            if ($field->type == 'foreignkey') {
                // Add the foreignkey constraints
                $referto = new $val['model']();
                $constraints[] = $alter_tbl.' DROP CONSTRAINT '.$this->getShortenedIdentifierName($table.'_'.$col.'_fkey');
            }
        }

        // Now for the many to many
        foreach ($manytomany as $many) {
            $omodel = new $cols[$many]['model']();
            $hay = array(strtolower($model->_a['model']), strtolower($omodel->_a['model']));
            sort($hay);
            $table = $this->con->pfx.$hay[0].'_'.$hay[1].'_assoc';
            $alter_tbl = 'ALTER TABLE '.$table;
            $constraints[] = $alter_tbl.' DROP CONSTRAINT '.$this->getShortenedIdentifierName($table.'_fkey1');
            $constraints[] = $alter_tbl.' DROP CONSTRAINT '.$this->getShortenedIdentifierName($table.'_fkey2');
        }
        return $constraints;
    }
}

