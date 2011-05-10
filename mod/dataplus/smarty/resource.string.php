<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     resource
 * Name:     string
 * Version:  1.1
 * Author:   Joshua Thijssen <jthijssen@noxlogic.nl>
 * Credits:
 * Purpose:  using php strings as smarty resouces
 * Input:
 *
 * Examples: $smarty->display ("string:<b>{$foo}</b>");
 * -------------------------------------------------------------
 */

function smarty_resource_string_source($tpl_name, &$tpl_source, &$smarty_obj) {
    $tpl_source = $tpl_name;
    return true;
}

function smarty_resource_string_timestamp($tpl_name, &$tpl_timestamp, &$smarty_obj) {
    $tpl_timestamp = time ();
    return true;
}

function smarty_resource_string_secure($tpl_name, &$smarty_obj) {
    // assume all templates are secure
    return true;
}

function smarty_resource_string_trusted($tpl_name, &$smarty_obj) {
    // not used for templates
}

// register the resource name "string"
$smarty->register_resource("string", array("smarty_resource_string_source",
                                           "smarty_resource_string_timestamp",
                                           "smarty_resource_string_secure",
                                           "smarty_resource_string_trusted"));