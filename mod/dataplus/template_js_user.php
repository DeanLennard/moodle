<?php
require_once("../../config.php");
require_once('sqlite3_db_dataplus.php');
require_once('dataplus_file_helper.php');

$dataplus_filehelper = new dataplus_file_helper($COURSE->id, $dataplus->id);

$id = required_param('id', PARAM_INT);
$mode = required_param('mode', null, PARAM_TEXT);

if (! $cm = get_coursemodule_from_id('dataplus', $id)) {
    print_error("Course Module ID was incorrect");
}

if (! $dataplus = $DB->get_record("dataplus", array("id"=>$cm->instance))) {
    print_error("Course module is incorrect");
}

$dataplus_filehelper = new dataplus_file_helper($dataplus->id,'js');
$dataplus_db = new sqlite3_db_dataplus($dataplus->id);

$template = $dataplus_db->get_template($mode);

print $template->js;
