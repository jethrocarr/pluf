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
 * A class to manage the migration of the code from one version to
 * another, upward or downward.
 *
 * You can directly use the migrate.php script.
 *
 * Simple example usage:
 *
 * <pre>
 * $m = new Pluf_Migration('MyApp');
 * $m->migrate();
 *
 * // Install the application MyApp
 * $m = new Pluf_Migration('MyApp');
 * $m->install();
 * // Uninstall the application MyApp
 * $m->unInstall();
 *
 * $m = new Pluf_Migration();
 * $m->migrate(); // migrate all the installed app to the newest version.
 *
 * $m = new Pluf_Migration();
 * $m->migrate(3); // migrate (upgrade or downgrade) to version 3
 * </pre>
 *
 */
class Pluf_Migration
{
    protected $app = ''; /**< Application beeing migrated. */
    public $apps = array(); /**< Applications which are going to be migrated. */
    public $to_version = null; /**< Target version for the migration. */
    public $dry_run = false; /**< Set to true to not act. */
    public $display = false; /**< Display on the console what is done. */

    /**
     * Create a new migration.
     *
     * @param mixed Application or array of applications to migrate.
     */
    public function __construct($app=null)
    {
        if (!is_null($app)) {
            if (is_array($app)) {
                $this->apps = $app;
            } else {
                $this->apps = array($app);
            }
        } else {
            $this->apps = Pluf::f('installed_apps');
        }
    }


    /**
     * Install the application.
     *
     * Basically run the base install function for each application
     * and then set the version to the latest migration.
     */
    public function install()
    {
        foreach ($this->apps as $app) {
            $this->installApp($app);
        }
        return true;
    }

    /**
     * Uninstall the application.
     */
    public function unInstall()
    {
        $apps = array_reverse($this->apps);
        foreach ($apps as $app) {
            $this->installApp($app, true);
        }
    }

    /**
     * Backup the application.
     *
     * @param string Path to the backup folder
     * @param string Backup name (null)
     */
    public function backup($path, $name=null)
    {
        foreach ($this->apps as $app) {
            $func = $app.'_Migrations_Backup_run';
            Pluf::loadFunction($func);
            if ($this->display) {
                echo($func."\n");
            }
            if (!$this->dry_run) {
                $ret = $func($path, $name); 
            }
        }
        return true;
    }
    
    /**
     * Restore the application.
     *
     * @param string Path to the backup folder
     * @param string Backup name 
     */
    public function restore($path, $name)
    {
        foreach ($this->apps as $app) {
            $func = $app.'_Migrations_Backup_restore';
            Pluf::loadFunction($func);
            if ($this->display) {
                echo($func."\n");
            }
            if (!$this->dry_run) {
                $ret = $func($path, $name); 
            }
        }
        return true;
    }
    
    /**
     * Run the migration.
     *
     */
    public function migrate($to_version=null)
    {
        $this->to_version = $to_version;
        foreach ($this->apps as $app) {
            $this->app = $app;
            $migrations = $this->findMigrations();
            // The run will throw an exception in case of error.
            $this->runMigrations($migrations); 
        }
        return true;
    }

    /**
     * Un/Install the given application.
     *
     * @param string Application to install.
     * @param bool Uninstall (false)
     */
    public function installApp($app, $uninstall=false)
    {
        if ($uninstall) {
            $func = $app.'_Migrations_Install_teardown';
        } else {
            $func = $app.'_Migrations_Install_setup';
        }
        $ret = true;
        Pluf::loadFunction($func);
        if ($this->display) {
            echo($func."\n");
        }
        if (!$this->dry_run) {
            $ret = $func(); // Run the install/uninstall
            if (!$uninstall) {
                // 
                $this->app = $app;
                $migrations = $this->findMigrations();
                if (count($migrations) > 0) {
                    $to_version = max(array_keys($migrations));
                } else {
                    $to_version = 0;
                }
                $this->setAppVersion($app, $to_version);
            } else {
                if ($app != 'Pluf') {
                    // If Pluf we do not have the schema info table
                    // anymore
                    $this->delAppInfo($app);
                }
            }
        }
        return $ret;
    }


    /**
     * Find the migrations for the current app.
     *
     * @return array Migrations names indexed by order.
     */
    public function findMigrations()
    {
        $migrations = array();
        if (false !== ($mdir = Pluf::fileExists($this->app.'/Migrations'))) {
            $dir = new DirectoryIterator($mdir);
            foreach($dir as $file) {
                $matches = array();
                if (!$file->isDot() && !$file->isDir()
                    && preg_match('#^(\d+)#', $file->getFilename(), $matches)) {
                    $info = pathinfo($file->getFilename());
                    $migrations[(int)$matches[1]] = $info['filename'];
                }
            }
        }
        return $migrations;
    }

    /**
     * Run the migrations.
     *
     * From an array of possible migrations, it will first get the
     * current version of the app and then based on $this->to_version
     * will run the migrations in the right order or do nothing if
     * nothing to be done.
     *
     * @param array Possible migrations.
     */
    public function runMigrations($migrations)
    {
        if (empty($migrations)) {
            return;
        }
        $current = $this->getAppVersion($this->app);
        if ($this->to_version === null) {
            $to_version = max(array_keys($migrations));
        } else {
            $to_version = $this->to_version;
        }
        if ($to_version == $current) {
            return; // Nothing to do
        }
        $the_way = 'up'; // Tribute to Pat Metheny
        if ($to_version > $current) {
            // upgrade
            $min = $current + 1;
            $max = $to_version;
        } else {
            // downgrade
            $the_way = 'do';
            $max = $current;
            $min = $to_version + 1;
        }
        // Filter the migrations
        $to_run = array();
        foreach ($migrations as $order=>$name) {
            if ($order < $min or $order > $max) {
                continue;
            }
            if ($the_way == 'up') {
                $to_run[] = array($order, $name);
            } else {
                array_unshift($to_run, array($order, $name));
            }
        }
        asort($to_run);
        // Run the migrations
        foreach ($to_run as $migration) {
            $this->runMigration($migration, $the_way);
        }
    }

    /**
     * Run the given migration.
     */
    public function runMigration($migration, $the_way='up')
    {
        $target_version = ($the_way == 'up') ? $migration[0] : $migration[0]-1;
        if ($this->display) {
            echo($migration[0].' '.$migration[1].' '.$the_way."\n");
        }
        if (!$this->dry_run) {
            if ($the_way == 'up') {
                $func = $this->app.'_Migrations_'.$migration[1].'_up';
            } else {
                $func = $this->app.'_Migrations_'.$migration[1].'_down';
            }
            Pluf::loadFunction($func);
            $func(); // Real migration run
            $this->setAppVersion($this->app, $target_version);
        } 
    }

    /**
     * Set the application version.
     *
     * @param string Application
     * @param int Version
     * @return true
     */
    public function setAppVersion($app, $version)
    {
        $gschema = new Pluf_DB_SchemaInfo();
        $sql = new Pluf_SQL('application=%s', $app);
        $appinfo = $gschema->getList(array('filter' => $sql->gen()));
        if ($appinfo->count() == 1) {
            $appinfo[0]->version = $version;
            $appinfo[0]->update();
        } else {
            $schema = new Pluf_DB_SchemaInfo();
            $schema->application = $app;
            $schema->version = $version;
            $schema->create();
        }
        return true;
    }

    /**
     * Remove the application information.
     *
     * @param string Application
     * @return true
     */
    public function delAppInfo($app)
    {
        $gschema = new Pluf_DB_SchemaInfo();
        $sql = new Pluf_SQL('application=%s', $app);
        $appinfo = $gschema->getList(array('filter' => $sql->gen()));
        if ($appinfo->count() == 1) {
            $appinfo[0]->delete();
        }
        return true;
    }

    

    /**
     * Get the current version of the app.
     *
     * @param string Application.
     * @return int Version.
     */
    public function getAppVersion($app)
    {
        try {
            $db =& Pluf::db();
            $res = $db->select('SELECT version FROM '.$db->pfx.'schema_info WHERE application='.$db->esc($app));
            return (int) $res[0]['version'];
        } catch (Exception $e) {
            // We should not be here, only in the case of nothing
            // installed. I am not sure if this is a good way to
            // handle this border case anyway. Maybe better to have an
            // 'install' method to run all the migrations in order.
            return 0;
        }
    }
}