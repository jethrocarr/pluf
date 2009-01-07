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
 * Render a template file.
 */
class Pluf_Template
{
    public $tpl = '';
    public $folders = array();
    public $cache = '';
    public $compiled_template = '';
    public $template_content = '';
    public $context = null;

    /**
     * Constructor.
     *
     * If the folder name is not provided, it will default to
     * Pluf::f('template_folders')
     * If the cache folder name is not provided, it will default to
     * Pluf::f('tmp_folder')
     *
     * @param string Template name.
     * @param string Template folder paths (null)
     * @param string Cache folder name (null)
     */
    function __construct($template, $folders=null, $cache=null)
    {
        $this->tpl = $template;
        if (is_null($folders)) {
            $this->folders = Pluf::f('template_folders');
        } else {
            $this->folders = $folders;
        }
        if (is_null($cache)) {
            $this->cache = Pluf::f('tmp_folder');
        } else {
            $this->cache = $cache;
        }
        if (defined('IN_UNIT_TESTS')) {
            if (!isset($GLOBALS['_PX_tests_templates'])) {
                $GLOBALS['_PX_tests_templates'] = array();
            } 
        }
    }

    /**
     * Render the template with the given context and return the content.
     *
     * @param Object Context.
     */
    function render($c=null)
    {
        if (defined('IN_UNIT_TESTS')) {
            $GLOBALS['_PX_tests_templates'][] = $this;
        }
        $this->compiled_template = $this->getCompiledTemplateName();
        if (!file_exists($this->compiled_template) or Pluf::f('debug')) {
            $compiler = new Pluf_Template_Compiler($this->tpl, $this->folders);
            $this->template_content = $compiler->getCompiledTemplate();
            $this->write();
        }
        if (is_null($c)) {
            $c = new Pluf_Template_Context();
        }
        $this->context = $c;
        ob_start();
        $t = $c;
        try {
            include $this->compiled_template;
        } catch (Exception $e) {
            ob_clean();
            throw $e;
        }
        $a = ob_get_contents();
        ob_end_clean();
        return $a;
    }

    /**
     * Get the full name of the compiled template.
     *
     * Ends with .phps to prevent execution from outside if the cache folder
     * is not secured but to still have the syntax higlightings by the tools
     * for debugging.
     *
     * @return string Full path to the compiled template
     */
    function getCompiledTemplateName()
    {
        // The compiled template not only depends on the file but also
        // on the possible folders in which it can be found.
        $_tmp = var_export($this->folders, true);
        return $this->cache.'/Pluf_Template-'.md5($_tmp.$this->tpl).'.phps';
    }

    /**
     * Write the compiled template in the cache folder.
     * Throw an exception if it cannot write it.
     *
     * @return bool Success in writing
     */
    function write() 
    {
        // mode "a" to not truncate before getting the lock
        $fp = @fopen($this->compiled_template, 'a'); 
        if ($fp !== false) {
            // Exclusive lock on writing
            flock($fp, LOCK_EX); 
            // We have the unique pointeur, we truncate
            ftruncate($fp, 0); 
            // Go back to the start of the file like a +w
            rewind($fp); 
            fwrite($fp, $this->template_content, strlen($this->template_content));
            // Lock released, read access is possible
            flock($fp, LOCK_UN);  
            fclose($fp);
            @chmod($this->compiled_template, 0777);
            return true;
        } else {
            throw new Exception(sprintf(__('Cannot write the compiled template: %s'), $this->compiled_template));
        }
        return false;
    }

    public static function markSafe($string)
    {
        return new Pluf_Template_SafeString($string, true);
    }
}

/**
 * Set a string to be safe for display.
 *
 * @param string String to be safe for display.
 * @return string Pluf_Template_SafeString 
 */
function Pluf_Template_unsafe($string)
{
    return new Pluf_Template_SafeString($string, true);
}

/**
 * Special htmlspecialchars that can handle the objects.
 *
 * @param string String proceeded by htmlspecialchars
 * @return string String like if htmlspecialchars was not applied
 */
function Pluf_Template_htmlspecialchars($string)
{
    return htmlspecialchars((string)$string, ENT_COMPAT, 'UTF-8');
}

/**
 * Modifier plugin: Convert the date from GMT to local and format it.
 *
 * This is used as all the datetime are stored in GMT in the database.
 *
 * @param string $date input date string considered GMT
 * @param string $format strftime format for output ('%b %e, %Y')
 * @return string date in localtime
 */
function Pluf_Template_dateFormat($date, $format='%b %e, %Y') 
{
    if (substr(PHP_OS,0,3) == 'WIN') {
        $_win_from = array ('%e',  '%T',	   '%D');
        $_win_to   = array ('%#d', '%H:%M:%S', '%m/%d/%y');
        $format	= str_replace($_win_from, $_win_to, $format);
    }
    $date = date('Y-m-d H:i:s', strtotime($date.' GMT'));
    return strftime($format, strtotime($date));
}

/**
 * Modifier plugin: Format a unix time.
 *
 * Warning: date format is directly to be used, not consideration of
 * GMT or local time.
 *
 * @param int $time  input date string considered GMT
 * @param string $format strftime format for output ('Y-m-d H:i:s')
 * @return string formated time
 */
function Pluf_Template_timeFormat($time, $format='Y-m-d H:i:s') 
{
    return date($format, $time);
}


/**
 * Special echo function that checks if the string to output is safe
 * or not, if not it is escaped.
 *
 * @param mixed Input
 * @return string Safe to display in HTML.
 */
function Pluf_Template_safeEcho($mixed, $echo=true)
{
    if (!is_object($mixed) or 'Pluf_Template_SafeString' !== get_class($mixed)) {
        if ($echo) {
            echo htmlspecialchars((string) $mixed, ENT_COMPAT, 'UTF-8');
        } else {
            return htmlspecialchars((string) $mixed, ENT_COMPAT, 'UTF-8');
        }
    } else {
        if ($echo) {
            echo $mixed->value;
        } else {
            return $mixed->value;
        }
    }
}

/**
 * New line to <br /> returning a safe string.
 *
 * @param mixed Input
 * @return string Safe to display in HTML.
 */
function Pluf_Template_nl2br($mixed)
{
    if (!is_object($mixed) or 'Pluf_Template_SafeString' !== get_class($mixed)) {
        return Pluf_Template::markSafe(nl2br(htmlspecialchars((string) $mixed, ENT_COMPAT, 'UTF-8')));
    } else {
        return Pluf_Template::markSafe(nl2br($mixed->value));
    }
}

/**
 * Var export returning a safe string.
 *
 * @param mixed Input
 * @return string Safe to display in HTML.
 */
function Pluf_Template_varExport($mixed)
{
    return Pluf_Template_unsafe('<pre>'.Pluf_esc(var_export($mixed, true)).'</pre>');
}


/**
 * Display the date in a "6 days, 23 hours ago" style.
 */
function Pluf_Template_dateAgo($date, $f='withal')
{
    Pluf::loadFunction('Pluf_Date_Easy');
    $date = Pluf_Template_dateFormat($date, '%Y-%m-%d %H:%M:%S');
    if ($f == 'withal') {
        return Pluf_Date_Easy($date, null, 2, __('now'));
    } else {
        return Pluf_Date_Easy($date, null, 2, __('now'), false);
    }
}

/**
 * Display the time in a "6 days, 23 hours ago" style.
 */
function Pluf_Template_timeAgo($date, $f="withal")
{
    Pluf::loadFunction('Pluf_Date_Easy');
    $date = Pluf_Template_timeFormat($date);
    if ($f == 'withal') {
        return Pluf_Date_Easy($date, null, 2, __('now'));
    } else {
        return Pluf_Date_Easy($date, null, 2, __('now'), false);
    }
}

/**
 * Hex encode an email excluding the "mailto:".
 */
function Pluf_Template_safeEmail($email)
{
    $email = chunk_split(bin2hex($email), 2, '%');
    $email = '%'.substr($email, 0, strlen($email) - 1);
    return Pluf_Template::markSafe($email);
}