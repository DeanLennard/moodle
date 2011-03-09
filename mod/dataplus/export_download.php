<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */


//creates a zip archive containing module data for user's to download

header("Cache-Control: no-cache, must-revalidate");

require_once("../../config.php");
require_once('dataplus_file_helper.php');
require_once('sqlite3_db_dataplus.php');
require_once($CFG->libdir.'/filelib.php');

//Stand alone version of the necessary page setup code from dataplus/lib.php
$id = required_param('id', PARAM_INT);
$mode = optional_param('mode', null, PARAM_TEXT);
$cm = get_coursemodule_from_id('dataplus', $id);
$context = get_context_instance(CONTEXT_MODULE,$cm->id);

if (!$cm = get_coursemodule_from_id('dataplus', $id)) {
    print_error("Course Module ID was incorrect");
}

if (!$COURSE = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error("Course is misconfigured");
}

if (!$dataplus = $DB->get_record("dataplus", array("id"=>$cm->instance))) {
    print_error("Course module is incorrect");
}

$dataplus_filehelper = new dataplus_file_helper($dataplus->id);

$temp_path = $dataplus_filehelper->get_temp_path();
$image_fileinfo = $dataplus_filehelper->get_image_fileinfo();
$file_fileinfo = $dataplus_filehelper->get_file_fileinfo();
$tozip_path = $dataplus_filehelper->get_tozip_path();

//setup a zip archive
$zip_id = $COURSE->id.$USER->id.$dataplus->id;
$zip_filename = 'dataplus'.$dataplus->id.'.zip';
$zip_path = $temp_path.'/'.$zip_filename;
$to_zip_path = $dataplus_filehelper->get_tozip_path();
$zippacker = get_file_packer('application/zip');

mkdir($to_zip_path);

$zip_file_info = $dataplus_filehelper->get_zip_fileinfo();
$zip_file_info['itemid'] = $zip_id;
$zip_file_info['filename'] = $zip_filename;

$db_file_info = $dataplus_filehelper->get_file_fileinfo();
$db_file_info['filearea'] = 'dataplus';
$db_file_info['itemid'] = $dataplus->id;
$db_file_info['filename'] = (string) $dataplus->id .'.sqlite'; 

//make a copy of the database to prepare for download
$dataplus_filehelper->copy($db_file_info,$to_zip_path);
$database_file_path = $to_zip_path.'/'.$dataplus->id.'.sqlite';

//code for the download of a simple SQLite3 database or a CSV file
if ($mode == 'simple' || $mode == 'csv') {
    $currentgroup = groups_get_activity_group($cm);

    $dataplus_db = new sqlite3_db_dataplus($dataplus->id, false, $database_file_path);

    $cols = $dataplus_db->list_dataplus_table_columns(true);

    //If groups are being used, delete columns that are not used by the current group and
    //add files that are part of this group to the archive

    if ($currentgroup > 0) {
        foreach ($cols as $col) {
            if ($col->group_id != $currentgroup && !empty($col->group_id)) {
                $dataplus_db->delete_column($col->id);
            }
        }

        $parameters[0]->name = 'group_id';
        $parameters[0]->value = $currentgroup;
        $parameters[0]->operator = 'notequal';

        $parameters[1]->name = 'group_id';
        $parameters[1]->value = '0';
        $parameters[1]->operator = 'notequal';
        $parameters[1]->andor = 'AND';

        $parameters[2]->name = 'group_id';
        $parameters[2]->value = '';
        $parameters[2]->operator = 'notequal';
        $parameters[2]->andor = 'AND';

        $dataplus_db->delete_dataplus_record($parameters);

        $cols = $dataplus_db->list_dataplus_table_columns(true);

        foreach ($cols as $col) {
            if ($col->form_field_type == 'file' || $col->form_field_type == 'image') {
                $col_name = $col->name;

                $filenames = $dataplus_db->query_dataplus_database(array($col_name));

                foreach ($filenames as $fn) {
                    if (!empty($fn->$col_name)) {
                        if ($col->form_field_type == 'image') {
                            $from = $image_fileinfo;
                            $to   = $dataplus_filehelper->get_tozip_images_path() . "/" . $fn->$col_name;
                        } else {
                            $from = $file_fileinfo;
                            $to   = $dataplus_filehelper->get_tozip_files_path() . "/" . $fn->$col_name;
                        }

                        $from['fileinfo'] = $fn->$col_name;

                        $dataplus_filehelper->copy($from,$to);
                    }
                }
            }
        }
    }
    //If groups are not used, add all images and files to the archive
    else {
        $dataplus_filehelper->copy($image_fileinfo,$dataplus_filehelper->get_tozip_images_path());
        $dataplus_filehelper->copy($file_fileinfo,$dataplus_filehelper->get_tozip_files_path());
    }

    $i = 0;

    //remove user ids to ensure user identifiable data is not distributed and redundant group_ids

    $cols_to_rem = array('group_id');

    if (!(has_capability('mod/dataplus:downloadfull', $context))) {
        $cols_to_rem[] = 'creator_id';
        $cols_to_rem[] = 'last_update_id';
    } else {
        $dataplus_db->ids_to_usernames('creator_id');
        $dataplus_db->ids_to_usernames('last_update_id');  
    }

    foreach ($cols as $col) {
        if (in_array($col->name,$cols_to_rem)) {
            $dataplus_db->delete_column($col->id);
            unset($cols[$i]);
        }

        $i++;
    }

    //convert dates in the database from seconds from the Unix Epoch to UK date format.
    $dataplus_db->generate_UK_dates();

    //if csv is selected, generate the csv file.
    if ($mode == 'csv') {
        $all_records = $dataplus_db->query_dataplus_database();

        $output_file_name = $dataplus->id.'.csv';
        $output_path = $tozip_path.'/'.$output_file_name;

        $content = '';

        foreach ($cols as $col) {
            $content .= $col->label . ",";
        }

        $content = substr($content,0,(strlen($content)-1));
        $content .= "\r\n";

        foreach ($all_records as $record) {
            $row = '';

            foreach ($record as $field) {
                $field = str_replace(",","�",$field);
                $row .= $field . ",";
            }

            $row = str_replace("\r\n","",$row);
            $row = substr($row,0,(strlen($row)-1));
            $row .= "\r\n";
            $content .= $row;
        }

        fulldelete($tozip_path.'/'.$dataplus->id.'.sqlite');
        file_put_contents($output_path,$content);
        $dataplus_db = null;
    }
    //if simple, drop the supporting tables leaving only the core module data.
    else {
        $dataplus_db->drop_table('column');
        $dataplus_db->drop_table('templates');
    }
}
//if a full dataplus database is being downloaded, add all supporting files to the archive.
else if ((has_capability('mod/dataplus:downloadfull', $context))) {
    $dataplus_db = new sqlite3_db_dataplus($dataplus->id);

    $dataplus_filehelper->copy($image_fileinfo,$dataplus_filehelper->get_tozip_images_path());
    $dataplus_filehelper->copy($file_fileinfo,$dataplus_filehelper->get_tozip_files_path());
} else {
    print_error("Export selections misset or you do not have the correct permissions to proceed");
}

if (!isset($output_path)) {
    $output_path = $database_file_path;
}

if (!isset($output_file_name)) {
    $output_file_name = $dataplus_db->get_db_file_name();
}

$file_to_zip['images'] = $dataplus_filehelper->get_tozip_images_path();
$file_to_zip['files'] = $dataplus_filehelper->get_tozip_files_path();
$file_to_zip[$output_file_name] = $output_path;

$zippacker->archive_to_storage($file_to_zip,
                               $zip_file_info['contextid'],
                               $zip_file_info['component'],
                               $zip_file_info['filearea'],
                               $zip_file_info['itemid'],
                               $zip_file_info['filepath'],
                               $zip_file_info['filename']);
//var_dump($file_to_zip);
//generate the url for the archive and trigger download.
$download_url = $dataplus_filehelper->get_zip_file_path($zip_id);
$SESSION->dataplus_file_to_delete = array('filename' => $zip_file_info['filename'],
                                          'itemid' => $zip_file_info['itemid'],
                                          'type' => $zip_file_info['filearea']);
$dataplus_filehelper->close();
header('Location:' . $download_url);