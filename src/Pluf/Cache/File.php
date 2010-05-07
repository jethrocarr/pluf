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
 * File based cache.
 *
 * Info are stored in the 'cache_file_folder' folder. In the folder, 2
 * subdirectories are created based on the md5 of the key.
 */
class Pluf_Cache_File extends Pluf_Cache
{
    public function __construct()
    {
        if (false == Pluf::f('cache_file_folder', false)) {
            throw new Pluf_Exception_SettingError('"cache_file_folder" setting not defined.');
        }
    }

    /**
     * Set a value in the cache.
     *
     * @param string Key to store the information
     * @param mixed Value to store
     * @param int Timeout in seconds (null)
     * @return bool Success
     */
    public function set($key, $value, $timeout=null)
    {
        $fname = $this->_keyToFile($key);
        $dir = dirname($fname);
        if ($timeout == null) $timeout = Pluf::f('cache_timeout', 300);
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        $expire = time()+$timeout;
        $success = file_put_contents($fname, $expire."\n".serialize($value), LOCK_EX);
        return (false === $success) ? false : true;
    }

    /**
     * Get value from the cache.
     *
     * @param string Key to get the information
     * @param mixed Default value to return if cache miss (null)
     * @param mixed Stored value or default
     */
    public function get($key, $default=null)
    {
        $fname = $this->_keyToFile($key);
        if (!file_exists($fname)) return $default;
        list($timeout, $content) = explode("\n", file_get_contents($fname), 2);
        if ($timeout < time()) {
            @unlink($fname);
            return $default;
        }
        return unserialize($content);
    }

    /**
     * Convert a key into a path to a file.
     *
     * @param string Key
     * @return string Path to file
     */
    public function _keyToFile($key)
    {
        $md5 = md5($key);
        return Pluf::f('cache_file_folder').'/'.substr($md5, 0, 2)
            .'/'.substr($md5, 2, 2).'/'.substr($md5, 4);
    }
}
