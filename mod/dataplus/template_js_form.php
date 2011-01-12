<?php
define('NO_MOODLE_COOKIES', true);

require_once("../../config.php");
$lifetime  = 600;  
$PAGE->set_url('/mod/dataplus/template_js_form.php');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
    header('Cache-control: max_age = '. $lifetime);
    header('Pragma: ');
    header('Content-type: text/css'); 
echo "dataplusShowHideTemplateFormElement('id_css','dataplus_css_link','" . get_string('css_show', 'dataplus') . "','" . get_string('css_hide', 'dataplus') . "');";
echo "dataplusShowHideTemplateFormElement('id_javascript','dataplus_js_link','" . get_string('javascript_show', 'dataplus') . "','" . get_string('javascript_hide', 'dataplus') . "');";