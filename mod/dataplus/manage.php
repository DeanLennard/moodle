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

/**
 * generates the screen for managing the fields in the database.
 *
 * @param string $msg
 */
function dataplus_manage($msg = null){
    global $dataplus_db, $CFG, $id, $groupmode;

    require_once('manage_form.php');

    if (!is_null($msg)) {
        echo "<p>{$msg}</p>";
    }

    $mform = new dataplus_manage_form("{$CFG->wwwroot}/mod/dataplus/manage.php?id={$id}&mode=dbmanage");
    $parameters = dataplus_get_restricted_groups_parameters();	
    $columns = $dataplus_db->list_dataplus_table_columns(false, $parameters);

    //mform require the form fields to be defined before it checks post data

    /* if the database has no user defined columns, then generate a form to allow the creation 
     * of up to 4 fields, otherwise one field
     */

    if (empty($columns)) {
        $field_no = 4;
        $mform->define_fields($field_no);
    } else {
        $mform->define_fields(1);
    }

    if ($form = $mform->get_data()) {
        dataplus_log('createcolumn');

        $i = 0;
        $exists = false;

        // if there are no fields in the database, check that none of the new fields have the same name as each other
        if (empty($columns)) {
            for ($i=0; $i<$field_no; $i++) {
                $var_name = 'fieldname' . $i;
                $v = $i+1;

                while ($v<$field_no) {
                    $var_name2 = 'fieldname'.$v;

                    if ($form->$var_name2!='' && $form->$var_name == $form->$var_name2) {
                        $exists = 'FORMFIELDSSAME';
                        break;
                    }
                    $v++;
                }
            }
        }
        // if there are fields, check the new/edited fields doesn't have the same name as an existing field
        else {
            while (true) {
                $var_name = 'fieldname' . $i;

                if (isset($form->$var_name)) {
                    $name   = $dataplus_db->create_valid_object_name($form->$var_name);
                    if ($dataplus_db->check_column_exists($name)) {
                        $exists = 'FIELDEXISTS';
                    }
                }

                if ($exists == 'FIELDEXISTS' || !isset($form->$var_name)) {
                    break;
                }
                $i++;
            }
        }

        if ($exists == 'FIELDEXISTS') {
            echo get_string('labelexists', 'dataplus');
        } else if ($exists == 'FORMFIELDSSAME') {
            echo get_string('twosame', 'dataplus');
        } else {
            $i = 0;
            //add new fields until no new fields can be found in $form
            while (true) {
                $var_name = 'fieldname'.$i;
                $var_type = 'fieldtype'.$i;
                $var_multiple = 'fieldmultiple'.$i;
                $var_options = 'fieldoptions'.$i;
                $var_group_id = 'group_id'.$i;

                $options = null;

                if (isset($form->$var_name)) {
                    if ($form->$var_name != '') {
                        if ($form->$var_type == 'menu') {
                            if (!empty($form->$var_multiple)) {
                                $form->$var_type = 'menumultiple';
                            } else {
                                $form->$var_type = 'menusingle';
                            }

                            $options = $form->$var_options;
                        }

                        if(isset($form->$var_group_id)){
                            $group_id = $form->$var_group_id;
                        } else {
                            $group_id = '0';
                        }

                        $result = $dataplus_db->add_column($form->$var_name, $form->$var_type, $options, $group_id);
                    }
                } else {
                    break;
                }

                $i++;
            }

            //Trash the form and POST global and create a new instance, this ensures the form will have one field and post data is not displayed.
            $_POST = null;
            $mform = new dataplus_manage_form("{$CFG->wwwroot}/mod/dataplus/manage.php?id={$id}");
            $mform->define_fields(1);
            $columns = $dataplus_db->list_dataplus_table_columns(false, $parameters);
        }
    }

    // show a table of existing columns
    if (!empty($columns)) {
        $str_edit   = get_string('edit');
        $str_delete = get_string('delete');

        $table = new html_table();

        if ($groupmode > 0) {
            $table->head = array(get_string('fieldname', 'dataplus'), get_string('fieldtype', 'dataplus'), get_string('group', 'dataplus'), get_string('actions', 'dataplus'));
        } else {
            $table->head = array(get_string('fieldname', 'dataplus'), get_string('fieldtype', 'dataplus'), get_string('actions', 'dataplus'));	    		
        }

        foreach ($columns as $column) {
            if (dataplus_check_groups($column, true)) {
                $icons = "<a title=\"{$str_edit}\" href=\"manage.php?id={$id}&amp;mode=edit&amp;fid={$column->id}\">
                          <img src=\"{$CFG->wwwroot}/pix/t/edit.gif\" class=\"iconsmall\" alt=\"{$str_edit}\" /></a>
                          <a title=\"{$str_delete}\" href=\"manage.php?id={$id}&amp;mode=delete&amp;fid={$column->id}\">
                          <img src=\"{$CFG->wwwroot}/pix/t/delete.gif\" class=\"iconsmall\" alt=\"{$str_delete}\" /></a>";
            } else {
                $icons = '';
            }

            if($groupmode > 0){
                $table->data[] = array($column->label, $dataplus_db->get_field_type_description($column->form_field_type),dataplus_get_group_name($column->group_id),$icons);
            } else {
                $table->data[] = array($column->label, $dataplus_db->get_field_type_description($column->form_field_type),$icons);
            }
        }

        echo html_writer::table($table);
    }

    echo '<br/>';

    $mform->display();
}


/**
 * generates the screen for editing a field
 *
 */
function dataplus_edit(){
    global $dataplus_db, $CFG, $id, $dataplus_filehelper;

    require_once('manage_form.php');

    $fid = optional_param('fid', null, PARAM_INT);

    $mform = new dataplus_manage_form("{$CFG->wwwroot}/mod/dataplus/manage.php?id={$id}&mode=editsubmit&fid={$fid}");

    $mform->define_fields(1,'edit');

    //if the editing form is cancelled, go back to the manage screen
    if ($mform->is_cancelled() || empty($fid)) {
        $_POST = null;
        dataplus_manage();
        return;
    }

    //check the current group can edit this field
    $existing = $dataplus_db->get_column_details($fid);

    if (!dataplus_check_groups($existing, true, true)) {
        return;
    }

    //if form submitted, alter the existing column and return to the manage screen
    if ($form = $mform->get_data()) {
        dataplus_log('editcolumn');

        $name = $existing->name;
        $type = $existing->form_field_type;

        if ($type!=$form->fieldtype0) {
            if ($type == 'file' || $type == 'image') {
                $dataplus_filehelper->delete_column_files($name,$type);
            }
        }

        $details->id = $fid;
        $details->label = $form->fieldname0;

        if ($form->fieldtype0 == 'menu') {
            if (!empty($form->fieldmultiple0)) {
                $details->form_field_type = 'menumultiple';
            } else {
                $details->form_field_type= 'menusingle';
            }

            $details->form_field_options = $form->fieldoptions0;
        } else {
            $details->form_field_type = $form->fieldtype0;
        }

        if (isset($form->group_id0)) {
            $details->group_id = $form->group_id0;
        }

        $result = $dataplus_db->alter_column($details);

        if ($result === "COLUMNEXISTS") {
            $msg = get_string('samename', 'dataplus');
        } else {
            $msg = get_string('fieldedited', 'dataplus');
        }

        $_POST = null;

        dataplus_manage($msg);
        return;
    }

    //display the editing form.
    $column_details = $dataplus_db->get_column_details($fid);

    if ($column_details->form_field_type == 'menumultiple') {
        $column_details->multiple = 'checked';
    }

    if ($column_details->form_field_type == 'menusingle' || $column_details->form_field_type == 'menumultiple') {
        $column_details->form_field_type = 'menu';
    }

    $defaultvals = array(
        'fieldname0' => $column_details->label,
        'fieldtype0' => $column_details->form_field_type);

    if (isset($column_details->multiple)) {
        $defaultvals['fieldmultiple0'] = $column_details->multiple;
    }

    if (isset($column_details->form_field_options)) {
        $defaultvals['fieldoptions0'] = preg_replace("/^\r/"," \r",$column_details->form_field_options);
    }

    if(isset($column_details->group_id)){
        $defaultvals['group_id0'] = $column_details->group_id;
    }

    $mform->set_data($defaultvals);
    $mform->display();

    $str_return = get_string('returntofields', 'dataplus');
}


/**
 * generates the screen to delete fields.
 *
 */
function dataplus_delete(){
    global $dataplus_db, $CFG, $id, $groupmode, $dataplus_filehelper;

    require_once('delete_form.php');

    $fid = optional_param('fid', NULL, PARAM_INT);

    //check the current group can delete this field
    $col_details = $dataplus_db->get_column_details($fid);

    if (!dataplus_check_groups($col_details, true, true)) {
        return;
    }

    $mform = new dataplus_delete_form("{$CFG->wwwroot}/mod/dataplus/manage.php?id={$id}&mode=deletesubmit&fid={$fid}");

    if($mform->is_cancelled() || empty($fid)) {
        dataplus_manage();
        return;
    }

    //if the delete form has been submitted...
    if ($form = $mform->get_data()) {
        dataplus_log('deletecolumn');

        $form_field_type = $col_details->form_field_type;

        //if the field had supporting files, delete them.
        if ($form_field_type == 'image' || $form_field_type == 'file') {
            $dataplus_filehelper->delete_column_files($col_details->name,$col_details->form_field_type);
        }

        $del = $dataplus_db->delete_column($fid);

        if (!$del) {
            $msg = get_string('actionfailed', 'dataplus');
            dataplus_manage($msg);
            return;
        }

        $msg = get_string('fielddeleted', 'dataplus');

        //when delete is complete, go to the manage screen...
        dataplus_manage($msg);
        return;
    }

    $column_details = $dataplus_db->get_column_details($fid);

    $table = new html_table();

    // no form has been submitted, so display a table with the column detail and the delete form
    if ($groupmode > 0) {
        $table->head = array(get_string('fieldname', 'dataplus'), get_string('fieldtype', 'dataplus'), get_string('group', 'dataplus'));
    } else {
        $table->head = array(get_string('fieldname', 'dataplus'), get_string('fieldtype', 'dataplus'));
    }

    if ($groupmode > 0) {
        $table->data[] = array($column_details->label, $dataplus_db->get_field_type_description($column_details->form_field_type),dataplus_get_group_name($column_details->group_id));
    } else {
        $table->data[] = array($column_details->label, $dataplus_db->get_field_type_description($column_details->form_field_type));
    }

    echo html_writer::table($table);
    echo '<br/>';

    $mform->display();
}

dataplus_base_setup();
dataplus_page_setup('/mod/dataplus/manage.php',dataplus_get_querystring_vars(),get_string('managedatabase','dataplus'));

//if we're in dbsetup mode, don't show navigational tabs.
if ($mode!='dbsetup') {
    $currenttab = 'manage';

    include('tabs.php');
}

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    $group = optional_param('group', null, PARAM_TEXT);	

    if ($mode=='edit' || ($mode=='editsubmit' && is_null($group))) {
        dataplus_edit();
    } else if($mode=='delete' || ($mode=='deletesubmit' && is_null($group))) {
        dataplus_delete();
    } else{
        dataplus_manage();
    }
} else {
    print_error('capablilty_manage_database','dataplus', $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id='.$id);
}

echo $OUTPUT->footer();
dataplus_base_close();