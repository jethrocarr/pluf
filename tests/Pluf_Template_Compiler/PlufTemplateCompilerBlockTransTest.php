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

error_reporting(E_ALL | E_STRICT);

$path = dirname(__FILE__).'/../../src/';
set_include_path(get_include_path().PATH_SEPARATOR.$path);

require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/IncompleteTestError.php';

require_once 'Pluf.php';

class PlufTemplateCompilerBlockTransTest extends PHPUnit_Framework_TestCase {
    
    protected function setUp()
    {
        Pluf::start(dirname(__FILE__).'/../conf/pluf.config.php');
    }

    public function testCompile()
    {
        return '';
        $compiler = new Pluf_Template_Compiler('dummy', array(), false);
        $compiler->templateContent = '{blocktrans $count, $toto}We have one {$toto} element.{plural}We have {$counter} {$toto} elements.{/blocktrans}';
        $this->assertEquals('<?php $_b_t_c=$t->_vars[\'count\']; ob_start(); ?>We have one %2$s element.<?php $_b_t_s=ob_get_contents(); ob_end_clean(); ob_start(); ?>We have %1$d %2$s elements.<?php $_b_t_p=ob_get_contents(); ob_end_clean(); echo(sprintf(_n($_b_t_s, $_b_t_p, $_b_t_c), $_b_t_c, Pluf_Template_safeEcho($t->_vars[\'toto\'], false))); ?>',
                            $compiler->getCompiledTemplate());

    }

    public function testExtract()
    {
        $compiler = new Pluf_Translation_TemplateExtractor('dummy', array(), false);
        $compiler->templateContent = 'not in block {blocktrans $count, $toto}We have one {$toto} {$titi|nl2br} element.{plural}We have {$counter} {$toto} elements.{/blocktrans} not in block {trans \'toto\'} not in block {blocktrans}simple'."\n".' block{/blocktrans} sad {trans "youpla"}';
        //print $compiler->compile();
    }

    public function testCompileComplex()
    {
        $compiler = new Pluf_Template_Compiler('dummy', array(), false);
        $compiler->templateContent = '
    <h1>{trans "Pluf internationalization"}</h1>
    {assign $n_methods = $methods.count()}
    <p>{blocktrans $n_methods}To translate your code, use the following method:{plural}To translate your code, use one of the {$n_methods} methods:{/blocktrans}</p>
    <ul>
    {foreach $methods as $method}
       <li>{blocktrans}Name: {$method.name}, Description: {$method.description}.{/blocktrans}</li>
    {/foreach}
    </ul>
    </html>';
        //echo $compiler->getCompiledTemplate();
    }

}