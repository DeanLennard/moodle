<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/pclzip/pclzip.lib.php');

/*
 * Generate the import screen and process form submissions
 */
function dataplus_import(){
    global $CFG, $id, $dataplus_db, $dataplus, $dataplus_filehelper, $mode;

    require_once('import_form.php');
    $dataplus_db->validate_database();

    $url = "{$CFG->wwwroot}/mod/dataplus/import.php?id={$id}";

    //if the user is setting up the instance and has not tried to import an existing db...
    if ($mode == 'dbsetup' && empty($_POST)) {
        $url .= "&mode={$mode}";
    }

    $mform = new dataplus_import_form($url);

    if (!$mform->is_cancelled() && $form = $mform->get_data()) {
        //if something has been uploaded, put it in the temp directory...
        $temp_path = $dataplus_filehelper->get_temp_path();

        $mform->_upload_manager->upload_manager('importfile',false,true);
        $mform->_upload_manager->save_files($temp_path);

        $filename = $mform->_upload_manager->get_new_filename();

        $filenameSp = explode('.',$filename);

        //if the uploaded file does not appear to be a zip archive, get an error message
        if ($filenameSp[1] != 'zip') {
            $valid = get_string('validate_file_suffix','dataplus');
        }
        //otherwise, unzip the archive...
        else {
            unzip_file($temp_path . '/' . $filename);

            $hande = opendir($temp_path);

            $db_name = null;

            //find the name of the SQLite3 database
            while (false !== ($file = readdir($hande))) {
                $filenameSp = explode('.',$file);

                if (isset($filenameSp[1]) && $filenameSp[1] == 'sqlite') {
                    $db_name = $filenameSp[0];
                    break;
                }
            }

            if (!is_null($db_name)) {
                //run the database validation
                $upload_db = new sqlite3_db_dataplus($db_name);
                $valid = $upload_db->validate_database();

                if (isset($form->remgroups) && $form->remgroups == 1) {
                    $column[0]->name = 'group_id';
                    $column[0]->value = '';

                    $upload_db->update_record('content',$column);
                }

                if ($CFG->wwwroot != $upload_db->get_file_db_domain()) {
                    $column[0]->name = 'last_update_id';
                    $column[0]->value = '-1';

                    $upload_db->update_record('content',$column);

                    $column[0]->name = 'creator_id';
                    $column[0]->value = '-1';

                    $upload_db->update_record('content',$column);
                }
            } else {
                $valid = get_string('validate_no_db','dataplus');
            }
        }

        //if the database is valid, copy it from the temp dir to the root module instance and print a confirmation
        if ($valid === true) {
            $dataplus_db->delete_db();
            $dataplus_filehelper->copy($temp_path,$dataplus_filehelper->get_fileinfo(), array($filename,'lock.txt'));
            $path = $dataplus_filehelper->get_path();
            rename($path.'/'.$file, $path.'/'.$dataplus->id.'.sqlite');
            echo '<p>'.get_string('importcomplete', 'dataplus').'</p>';
        }
        //if it's not valid, print an error message
        else {
            echo '<p>'.$valid.'</p>';
        }
    }

    $mform->display();
}

dataplus_base_setup();
dataplus_page_setup('/mod/dataplus/import.php',dataplus_get_querystring_vars(),get_string('export','dataplus'));

//don't show the navigation tabs if we're in setup mode.
if ($mode!='dbsetup' || !empty($_POST)) {
    $currenttab = 'import';
    include('tabs.php');
}

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    dataplus_import();
} else {
    print_error('capablilty_edit_template','dataplus', $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
}

echo $OUTPUT->footer();
dataplus_base_close();