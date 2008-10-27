<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume CMS, a website management application.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume CMS is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume CMS is distributed in the hope that it will be useful,
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
 * For each model having a 'foreignkey' or a 'manytomany' colum, details
 * must be added here. These details are used to generated the methods
 * to retrieve related models from each model.
 */

$m = array();
$m['Pluf_User'] = array('relate_to_many' => array('Pluf_Group', 'Pluf_Permission'));
$m['Pluf_Group'] = array('relate_to_many' => array('Pluf_Permission'));
$m['Pluf_Message']  = array('relate_to' => array('Pluf_User'), );
$m['Pluf_RowPermission']  = array('relate_to' => array('Pluf_Permission'), );
$m['Pluf_Search_Occ']  = array('relate_to' => array('Pluf_Search_Word'), );
return $m;
