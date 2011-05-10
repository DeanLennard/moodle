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

define('NO_MOODLE_COOKIES', true);

require_once("../../config.php");
$lifetime  = 600;
$PAGE->set_url('/mod/dataplus/template_js_form.php');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
    header('Cache-control: max_age = '. $lifetime);
    header('Pragma: ');
    header('Content-type: text/css');
echo "dataplusShowHideTemplateFormElement('id_css','dataplus_css_link','" . get_string('css_show',
    'dataplus') . "','" . get_string('css_hide', 'dataplus') . "');";
echo "dataplusShowHideTemplateFormElement('id_javascript','dataplus_js_link','" .
    get_string('javascript_show', 'dataplus') . "','" . get_string('javascript_hide', 'dataplus') .
    "');";