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

class Pluf_Tests_Dispatch_Dispatcher extends UnitTestCase 
{
    protected $views = array();

    function __construct() 
    {
        parent::__construct('Test the dispatching.');
    }

    function setUp()
    {
        $this->views = $GLOBALS['_PX_views'];
    }

    function tearDown()
    {
        $GLOBALS['_PX_views'] = $this->views;
    }

    function hello()
    {
        return true;
    }

    function hello1()
    {
        return 1;
    }

    function hello2()
    {
        return 2;
    }

    function hello3()
    {
        return 3;
    }

    function hello4()
    {
        return 4;
    }

    function testSimple()
    {
        $GLOBALS['_PX_views'] = array(
                 array(
                       'regex' => '#^/hello/$#',
                       'base' => '',
                       'model' => 'Pluf_Tests_Dispatch_Dispatcher',
                       'method' => 'hello'
                       )
                 );
        $req1 = (object) array('query' => '/hello/'); // match
        $req2 = (object) array('query' => '/hello'); // match second pass
        $req3 = (object) array('query' => '/hello/you/'); // no match
        $this->assertIdentical(true, Pluf_Dispatcher::match($req1));
        $this->assertIsA(Pluf_Dispatcher::match($req2), 
                         'Pluf_HTTP_Response_Redirect');
        $this->assertIsA(Pluf_Dispatcher::match($req3), 
                         'Pluf_HTTP_Response_NotFound');
    }

    function testRecursif()
    {
        $GLOBALS['_PX_views'] = array(
                 array(
                       'regex' => '#^/hello/#',
                       'base' => '',
                       'sub' => array(
                                      array(
                                            'regex' => '#^world/$#',
                                            'base' => '',
                                            'model' => 'Pluf_Tests_Dispatch_Dispatcher',
                                            'method' => 'hello'
                                            )
                                      ),
                       ),
                 array(
                       'regex' => '#^/hello1/#',
                       'base' => '',
                       'sub' => array(
                                      array(
                                            'regex' => '#^world/$#',
                                            'base' => '',
                                            'model' => 'Pluf_Tests_Dispatch_Dispatcher',
                                            'method' => 'hello1'
                                            )
                                      ),
                       ),
                 array(
                       'regex' => '#^/hello2/#',
                       'base' => '',
                       'sub' => array(
                                      array(
                                            'regex' => '#^world/$#',
                                            'base' => '',
                                            'model' => 'Pluf_Tests_Dispatch_Dispatcher',
                                            'method' => 'hello2'
                                            )
                                      ),
                       ),
                                      );
        $req1 = (object) array('query' => '/hello/world/'); // match
        $req2 = (object) array('query' => '/hello/world'); // match second pass
        $req3 = (object) array('query' => '/hello/you/'); // no match
        $h1 = (object) array('query' => '/hello1/world/'); // match
        $h2 = (object) array('query' => '/hello2/world/'); // match
        $this->assertIdentical(true, Pluf_Dispatcher::match($req1));
        $this->assertIdentical(1, Pluf_Dispatcher::match($h1));
        $this->assertIdentical(2, Pluf_Dispatcher::match($h2));
        $this->assertIsA(Pluf_Dispatcher::match($req2), 
                         'Pluf_HTTP_Response_Redirect');
        $this->assertIsA(Pluf_Dispatcher::match($req3), 
                         'Pluf_HTTP_Response_NotFound');
        Pluf::loadFunction('Pluf_HTTP_URL_reverse');
        $this->assertEqual('/hello/world/',
                           Pluf_HTTP_URL_reverse('Pluf_Tests_Dispatch_Dispatcher::hello'));
        $this->assertEqual('/hello1/world/',
                           Pluf_HTTP_URL_reverse('Pluf_Tests_Dispatch_Dispatcher::hello1'));
        $this->assertEqual('/hello2/world/',
                           Pluf_HTTP_URL_reverse('Pluf_Tests_Dispatch_Dispatcher::hello2'));
    }


}