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
 * SQLite connection class
 */
class Pluf_DB_SQLite
{
    public $con_id;
    public $pfx = '';
    private $debug = false;
    /** The last query, set with debug(). Used when an error is returned. */
    public $lastquery = '';
    public $engine = 'SQLite';
    public $type_cast = array();

    function __construct($user, $pwd, $server, $dbname, $pfx='', $debug=false)
    {
        Pluf::loadFunction('Pluf_DB_defaultTypecast');
        $this->type_cast = Pluf_DB_defaultTypecast();
        $this->debug = $debug;
        $this->pfx = $pfx;
        $this->debug('* SQLITE OPEN');
        $this->type_cast['Pluf_DB_Field_Compressed'] = array('Pluf_DB_CompressedFromDb', 'Pluf_DB_SQLite_CompressedToDb');
        // Connect and let the Exception be thrown in case of problem
        try {
            $this->con_id = new PDO('sqlite:'.$dbname);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get the version of the SQLite library.
     *
     * @return string Version string
     */
    function getServerInfo()
    {
        return $this->con_id->getAttribute(PDO::ATTR_SERVER_INFO);
    }

    /**
     * Log the queries. Keep track of the last query and if in debug mode
     * keep track of all the queries in 
     * $GLOBALS['_PX_debug_data']['sql_queries']
     *
     * @param string Query to keep track
     * @return bool true
     */
    function debug($query)
    {
        $this->lastquery = $query;
        if (!$this->debug) return true;
        if (!isset($GLOBALS['_PX_debug_data']['sql_queries'])) 
            $GLOBALS['_PX_debug_data']['sql_queries'] = array();
        $GLOBALS['_PX_debug_data']['sql_queries'][] = $query;
        return true;
    }

    function close()
    {
        $this->con_id = null;
        return true;
    }

    function select($query)
    {
        $this->debug($query);
        if (false === ($cur = $this->con_id->query($query))) {
            throw new Exception($this->getError());
        }
        return $cur->fetchAll(PDO::FETCH_ASSOC);
    }

    function execute($query)
    {
        $this->debug($query);
        if (false === ($cur = $this->con_id->exec($query))) {
            throw new Exception($this->getError());
        }
        return $cur;

    }

    function getLastID()
    {
        $this->debug('* GET LAST ID');
        return (int) $this->con_id->lastInsertId();;
    }

    /**
     * Returns a string ready to be used in the exception.
     *
     * @return string Error string
     */
    function getError()
    {
        $err = $this->con_id->errorInfo();
        $err[] = $this->lastquery;
        return implode(' - ', $err);
    }

    function esc($str)
    {
        return $this->con_id->quote($str);
    }

    /**
     * Quote the column name.
     *
     * @param string Name of the column
     * @return string Escaped name
     */
    function qn($col)
    {
        return '"'.$col.'"';
    }

    /**
     * Start a transaction.
     */
    function begin()
    {
        $this->execute('BEGIN');
    }

    /**
     * Commit a transaction.
     */
    function commit()
    {
        $this->execute('COMMIT');
    }

    /**
     * Rollback a transaction.
     */
    function rollback()
    {
        $this->execute('ROLLBACK');
    }

    function __toString()
    {
        return '<Pluf_DB_SQLite('.$this->con_id.')>';
    }

}


function Pluf_DB_SQLite_CompressedToDb($val, $con) 
{
    if (is_null($val)) {
        return 'NULL';
    }
    return 'X'.$con->esc(bin2hex(gzdeflate($val, 9)));
}
