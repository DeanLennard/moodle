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
require_once($CFG->libdir . '/rsslib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * gets the record number to start a page from (for paging)
 * 
 * @return int
 *
 */
function dataplus_get_page_start(){
    return optional_param('ps', 0, PARAM_INT);

}


/*
 * Gets the values to include any CSS or JavaScript associated with a template
 * 
 * @return string
 */
function dataplus_get_css_and_js($template){
    global $id, $mode;

    if (isset($css)) {
        $template->css = "/mod/dataplus/template_css_user.php?id=".$id."&mode=".$mode;
    } else {
        $template->css = "/mod/dataplus/template_css_{$mode}.css";
    }

    if (isset($template->js)) {
        $template->js = "'/mod/dataplus/template_js_user.php?id=".$id."&mode=".$mode;
    } else {
        $template->js = null;
    }

    if (isset($template->js_init)) {
        global $id, $mode;

        $template->js_init = $template->js_init;
    } else {
        $template->js_init = null;
    }

    return $template;
}


/**
 * Sets up the page for records view or single view
 * 
 * @param object $template
 * @param string $msg
 */
function dataplus_records_page_setup($template, $msg){
    if (empty($template->header)) {
        $template->header = dataplus_get_default_header();
    }

    if (empty($template->footer)) {
        $template->footer = dataplus_get_default_footer();
    }

    if (dataplus_allow_comments() && empty($template->comments)) {
        $template->comments = dataplus_get_default_comments();
    }

    $css_js = dataplus_get_css_and_js($template);

    dataplus_view_page_setup($template->js, $template->js_init, $template->css);

    //print any message that has been set
    if (!is_null($msg)) {
        echo "<p>{$msg}</p>";
    }
}


/**
 * Sets up the page for amend record
 * 
 * @param object $template
 * @param string $msg
 */
function dataplus_amendrecord_page_setup($template){
    $css_js = dataplus_get_css_and_js($template);

    dataplus_view_page_setup($css_js->js, $css_js->js_init, $css_js->css);
}


/**
 * prints out a set maximum number of records from a database for the 'view' or 'search' screens
 *
 * @param string $msg
 * @param array $parameters
 * @param array $order
 */
function dataplus_view_records($msg = null, $parameters = array(), $order = null){
    global $dataplus_db, $CFG, $cm, $dataplus, $mode, $id, $USER, $groupmode, $currentgroup;

    dataplus_log('view');
    //look in the database to see if there is a user created template to use for displaying records
    $template = $dataplus_db->get_template('view');

    //if no, get an automatically generated one
    if (empty($template)) {
        $template->record = dataplus_get_default_view_template(true);
    }

    if (empty($order) && isset($template->sortorder)) {
        $order = dataplus_create_sortarr_from_str($template->sortorder);
    }

    dataplus_records_page_setup($template,$msg);

    //print the link for amending a search, if in searchresults mode
    if ($mode == 'searchresults') {
        $str_amend = get_string('amendsearch', 'dataplus');

        echo "<div class=\"dataplus_search_amend\"><a title=\"{$str_amend}\" href=\"view.php?id={$id}&amp;mode=searchamend\">{$str_amend}</a></div>";
    }

    //get the start point for records on the page and the upper limit
    $limit['start'] = dataplus_get_page_start();
    $limit['number'] = (int) $dataplus->listperpage;
    
    //get any group restrictions for the parameters for queries
    $col_parameters = dataplus_get_restricted_groups_parameters();

    //merge with any specified search parameters
    $parameters = array_merge($parameters, $col_parameters);

    //...and query the database
    $results = $dataplus_db->query_dataplus_database(null,$parameters,$limit,$order);

    //print a message if the query has returned no results
    if (empty($results)) {
        if ($mode == 'searchresults') {
            $str = get_string('searchempty', 'dataplus');
        } else {
            $str = get_string('dbempty', 'dataplus');
        }

        echo "<div id=\"dataplus_empty_results\">{$str}</div>";
        
        return;
    }

    //print the record result
    dataplus_print_template_headerfooter_output('header', $template->header, $parameters, $col_parameters);
    dataplus_print_template_output($template->record,$results);
    dataplus_print_template_headerfooter_output('footer', $template->footer, $parameters, $col_parameters); 
}


 /**
 * prints a single record view
 *
 * @param string $msg
 */
function dataplus_view_single_record($msg = null){
    global $dataplus_db, $CFG, $cm, $dataplus, $mode, $id, $USER, $groupmode, $currentgroup;

    dataplus_log('view');

    $changecomment = optional_param('changecomment',0,PARAM_INT);

    //look in the database to see if there is a user created template to use for displaying records
    $template = $dataplus_db->get_template('single');

    //if no, get an automatically generated one
    if (empty($template)) {
        $template->record = dataplus_get_default_view_template(true);
    }

    dataplus_records_page_setup($template,$msg);

    //get any group restrictions for the parameters for queries
    $col_parameters = dataplus_get_restricted_groups_parameters();

    //add record id to the query if one is specified
    $record_id = optional_param('ri', null, PARAM_INT);

    if (!is_null($record_id)) {
        $parameters[0]->name = 'id';
        $parameters[0]->value = $record_id;
        $parameters[0]->operator ='equals';
        $parameters = array_merge($parameters, $col_parameters);

    //get the start point for records on the page and the upper limit    
        $limit['start'] = 0;
    } else {
        $parameters = $col_parameters;
        $limit['start'] = dataplus_get_page_start();
    }

    $limit['number'] = 1;

    //find an order from the template if one was specified.
    if (empty($order) && isset($template->sortorder)) {
        $order = dataplus_create_sortarr_from_str($template->sortorder);
    } else {
        $order = null;
    }

    //...and query the database
    $results = $dataplus_db->query_dataplus_database(null,$parameters,$limit,$order);

    if($changecomment == dataplus_get_comment_amend()){
        dataplus_amend_comment($results[0]->id);
    } else if ($changecomment == dataplus_get_comment_delete()){
        dataplus_delete_comment();
    }


    //print a message if the query has returned no results
    if(empty($results)){
        $str = get_string('dbnotfound', 'dataplus');

        echo "<div id=\"dataplus_empty_results\">{$str}</div>";

        return;
    }

    //print a message if the record is not accessible to the current group
    dataplus_check_groups($results[0],false,true);

    //print the record result
    dataplus_print_template_headerfooter_output('header', $template->header, $parameters);
    dataplus_print_template_output($template->record,$results);

    if (dataplus_allow_comments()) {
        $cui = $update_id = optional_param('cui', null, PARAM_INT);

        if ($changecomment == dataplus_get_comment_form()) {
            dataplus_print_template_comments_output($results[0]->id,$template->comments);
            dataplus_amend_comment($results[0]->id);
        } else if($changecomment == dataplus_get_comment_edit()){
            dataplus_print_template_comments_output($template->comments,$results[0]->id,$cui);
            dataplus_amend_comment($results[0]->id);
            dataplus_print_template_comments_output($template->comments,$results[0]->id,null,$cui);
        } else if($changecomment == dataplus_get_comment_delete_form()){
            dataplus_print_template_comments_output($template->comments,$results[0]->id,$cui);
            dataplus_delete_comment($template->comments);
            dataplus_print_template_comments_output($template->comments,$results[0]->id,null,$cui);
        } else {
            dataplus_print_template_comments_output($template->comments,$results[0]->id);
        }
    }

    dataplus_print_template_headerfooter_output('footer', $template->footer, $parameters);        
}


/**
 * Works out which view to go to after a record update
 * 
 * @param string $msg
 */
function resolve_view($msg = null){
    global $mode, $SESSION;

    $return_search = optional_param('rs', null, PARAM_TEXT);

    if ($return_search == 'true') {
        $mode = 'searchresults';
        $parameters = $SESSION->dataplus_search_parameters;
        $order = $SESSION->dataplus_search_order;

        dataplus_view_records($msg,$parameters,$order);
    } else { 
        $mode = optional_param('oldmode', 'view', PARAM_TEXT);

        if ($mode == 'single') {
            dataplus_view_single_record($msg);
        } else {
            dataplus_view_records($msg);
        }
    }
}


/**
 * generates screen for adding or editing a record and handles form submission
 */
function dataplus_amend_record(){
    global $dataplus_db, $CFG, $id, $mode, $dataplus_filehelper, $dataplus, $currentgroup, $SESSION, $context; 

    //check to see if there is a max number of records allowed and whether it has been reached
    if (($mode == 'insert' || $mode == 'insertbelowlimit') && dataplus_maximum_entry_limit_reached()) {
        dataplus_view_page_setup();

        echo '<p>' . get_string('maxentriesreached','dataplus') . '</p>';

        return;
    }

    //check to see if there is an id for a record to update (must have this for edit mode not to be ignored)
    $update_id = optional_param('ui', null, PARAM_INT);
    $template = $dataplus_db->get_template('addrecord');

    //if no, get an automatically generated one
    if (empty($template)) {
        $template->record = dataplus_get_default_addrecord_template(true);
    }

    //add some parameters that will eventually be used in the update SQL
    if (!empty($update_id)) {
        $parameters[0]->name = 'id';
        $parameters[0]->value = $update_id;
        $parameters[0]->operator ='equals';

        $prev_result = $dataplus_db->query_dataplus_database_single(null,$parameters);

        //if the current group doesn't have the right to alter the record, return
        if (!dataplus_check_groups($prev_result, true, true)) {
            return;
        }
    }

    require_once('record_form.php');

    if (!is_null($update_id)) {
        dataplus_log('update');

        //find out if the user has come from a search
        $return_search = optional_param('rs', null, PARAM_TEXT);
        $oldmode = optional_param('oldmode', 'view', PARAM_TEXT);
        
        $page_start = dataplus_get_page_start();

        $url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode={$mode}&ui={$update_id}&ps={$page_start}&oldmode={$oldmode}";
    
        if ($return_search == 'true') {
            $url .= '&rs=true';
        }

        if ($oldmode !='single') {
            $url .= "#{$update_id}";
        }
    } else {
        dataplus_log('insert');

        $url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode={$mode}";
    }

    $mform = new dataplus_manage_form($url);

    //get the fields that will be included in the form taking into account any group restrictions
    $col_parameters = dataplus_get_restricted_groups_parameters();  
    $columns = $dataplus_db->list_dataplus_table_columns(false,$col_parameters);

    if (!is_null($update_id)) {
        $mform->define_fields($columns, $mode, $template->record, $prev_result);
    } else {
        $mform->define_fields($columns, $mode, $template->record);
    }

    if ($mform->is_cancelled()) {
        resolve_view();
        return;
    }

    //handle form submission
    if (!$mform->is_cancelled() && $form = $mform->get_data()) {
        $text_special_cases = array('file','image','menumultiple');
        $results = array();

        //itterate through all the columns included in the form and add data to the results array for use in generating update SQL

        foreach ($columns as $column) {
            $col_name = $column->name;
            $i = sizeof($results);

            //...also upload files or images to the file system
            if ($column->form_field_type == 'image' || $column->form_field_type == 'file') {
                if ($column->form_field_type == 'image') {
                    $fileinfo = $dataplus_filehelper->get_image_fileinfo();
                } else {
                    $fileinfo = $dataplus_filehelper->get_file_fileinfo();
                }

                $draftitemid = file_get_submitted_draft_itemid($col_name);
                $fileinfo['filename'] = $draftitemid;

                file_prepare_draft_area($draftitemid, $context->id, 'mod_dataplus', $column->form_field_type, empty($mform->id)?null:$mform->id);
                file_save_draft_area_files($draftitemid, $context->id, 'mod_dataplus', $column->form_field_type, $draftitemid); 
                $results[$i]->value = $draftitemid;
            }

            //check for supporting fields for form_field_types that have them and add value to $results
            if (in_array($column->form_field_type,$dataplus_db->get_combi_fields_types())) {
                $fields = $dataplus_db->get_combi_fields();
                $v = 1;

                foreach ($fields[$column->form_field_type] as $field) {
                   $extra_name = $column->name.$field.$dataplus_db->get_supporting_suffix();
 
                   if ($column->form_field_type == 'image'  && $field == 'id') {
                       $value = $draftitemid;
                   } else if(!isset($form->$extra_name)){
                       continue;
                   } else {
                       $value = $form->$extra_name;
                   }

                   $results[($i+$v)]->name = $extra_name;
                   $results[($i+$v)]->value = $value;
                   $v++;
                }
            }

            //convert the array for menumultiple fields into suitable from for the database and MoodleForms
            if ($column->form_field_type == 'menumultiple' && isset($form->$col_name)) {
                $results[$i]->value = '';

                //<<MM>> is used to divide multiple values, everything else tried upset PHP, or Smarty, or...
                foreach ($form->$col_name as $v) {
                    if ($results[$i]->value != '') {
                        $results[$i]->value .= '<<MM>>';
                    }
                    $results[$i]->value .= $v;
                }
            }

            //for all other form fields, just add the value to results.
            if (!in_array($column->form_field_type,$text_special_cases) && isset($form->$col_name)) {
                $results[$i]->value = undo_escaping($form->$col_name);
            }

            //if a value has been set, add a name.
            if (isset($results[$i])) {
                $results[$i]->name = $column->name;
            }
        }

        //add group info if applicable
        if (isset($form->group_id) || is_null($update_id)) {
            $i = sizeof($results);
            $results[$i]->name  = 'group_id';

            if (isset($form->group_id)) {
                $results[$i]->value = $form->group_id;
            } else if(is_null($update_id)) {
                $results[$i]->value = $currentgroup;
            }
        }

        //if there's an update id available, use the results array for an SQL update, otherwise and insert.
        if(!is_null($update_id)) {
            $dataplus_db->update_dataplus_record($results,$parameters);

            resolve_view();
            return;
        } else {
            $dataplus_db->insert_dataplus_record($results);
            $msg = "<p>".get_string('recordadded', 'dataplus')."</p>";
        }

        $_POST = null;
        $mform = new dataplus_manage_form($url);
        $mform->define_fields($columns, $mode, $template->record);
    }  

    dataplus_amendrecord_page_setup($template);

    //if editing, include previous values in the form.
    if (!is_null($update_id)) {
        foreach ($prev_result as $name=>$value) {
            
            //if the field has multiple values, explode by the divider.
            if (strstr($value,'<<MM>>') !== false) {
                $value = explode("<<MM>>",$value);

                for ($i = 0; $i < sizeof($value); $i++) {
                    $value[$i] = dataplus_prepare_value($value[$i]);
                }
            } else {
                $value = dataplus_prepare_value($value);
            }

            $display_values[$name] = $value;
        }

        $mform->set_data($display_values);
    }

    if (isset($msg)) {
        print $msg;
    }

    $mform->display();
}


/**
 * generates the screen for deleting a record
 */
function dataplus_delete_record(){
    global $dataplus_db, $CFG, $id, $dataplus_filehelper, $SESSION, $mode;

    dataplus_log('delete');

    require_once('delete_form.php');

    //get the id of the record to delete
    $update_id = required_param('ui', PARAM_INT);

    //find out if the user has come from a search
    $return_search = optional_param('rs', null, PARAM_TEXT);
    
    //get the page start so we can take the user back where they came from when a delete is complete or cancelled
    $page_start = dataplus_get_page_start();

    /*
     * this cures a bug whereby if the current record is the only one on a page and the last in a resultset,
     * the view screen displays a message to see the database is empty because the page_start variable being
     * higher than the number of records causes an empty resultset to be returned.
     */ 
    if ($return_search == 'true') {
        $view_parameters = $SESSION->dataplus_search_parameters;
    } else {
        $view_parameters = dataplus_get_restricted_groups_parameters();
    }

    $current_records = $dataplus_db->count_dataplus_database_query($view_parameters);

    if ($page_start == ($current_records-1)) {
        $page_start = 0;
    }

    $oldmode = optional_param('oldmode', 'view', PARAM_TEXT);
    $url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode=delete&ui={$update_id}&ps={$page_start}&oldmode={$oldmode}";

    if (!is_null($return_search)) {
        $url .= "&rs=true";
    }

    $mform = new dataplus_delete_form($url);

    //if form submitted and cancelled, return to the view screen
    if ($mform->is_cancelled()) {
        resolve_view();
        return;
    }

    //get details for the columns in the record
    $columns = $dataplus_db->list_dataplus_table_columns();

    //get the record as it currently stands
    $parameters[0]->name     = 'id';
    $parameters[0]->value    = $update_id;
    $parameters[0]->operator = 'equals';

    $result = $dataplus_db->query_dataplus_database_single(null,$parameters);

    //if the group does not have the rights to delete the record, return.
    if (!dataplus_check_groups($result, true, true)) {
        return;
    }

    //if form is submitted then try and delete the record
    if ($form = $mform->get_data()) {
        //delete the record, display msg according to success or not
        if ($dataplus_db->delete_dataplus_record($parameters)) {
            //check to see if any of the columns are files or images and delete the files from the file system
            foreach ($result as $name=>$value) {
                foreach ($columns as $col) {
                    if ($col->name == $name && ($col->form_field_type == 'image' ||$col->form_field_type == 'file')) {
                        $dataplus_filehelper->delete_file($value,$col->form_field_type);
                    }
                }
            }

            $msg = get_string('recorddeleted', 'dataplus');
        } else {
            $msg = get_string('actionfailed', 'dataplus');
        }

        //when the delete is complete, return the user to the view screen, respecting any search results.
        resolve_view($msg);

        return;
    }

    //if no form submission, then display the record to be deleted and the delete form.
    $template = $dataplus_db->get_template($oldmode);

    if (empty($template)) {
        $template->record = dataplus_get_default_view_template(true);
    }

    dataplus_view_page_setup();
    echo "<p><strong>" . get_string('deleterecord', 'dataplus') . "</strong></p>";
    dataplus_print_template_output($template->record,$result,true);
    $mform->display();
}


/**
 * generates the screen for searching records (advanced or simple)
 *
 */
function dataplus_search_records(){
    global $dataplus_db, $CFG, $id, $SESSION, $mode, $cm, $id;

    dataplus_log('search');

    require_once('search_form.php');

    $mform = new dataplus_search_form("{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode={$mode}");

    //get the group restriction parameters for use in queries.
    $col_parameters = dataplus_get_restricted_groups_parameters();

    //get the columns and supporting fields to be displayed in the search form (taking group restrictions into account)
    $columns = $dataplus_db->list_dataplus_table_columns(false,$col_parameters);
    $supporting_fields = $dataplus_db->detail_content_table_supporting_columns();

    //if we're amending a search, get the search type used previously from the session, otherwise use the mode
    if ($mode == 'searchamend' &&  property_exists($SESSION,'dataplus_formtype') &&  $SESSION->dataplus_formtype == 'searchadvanced') {
        $formtype = $SESSION->dataplus_formtype;
    } else {
        $formtype = $mode;
    }

    //set the session form type
    if ($mode =='search' || $mode == 'searchadvanced') {
        $SESSION->dataplus_formtype = $formtype;
    }

    //define the fields used in the search form
    $mform->define_fields($columns,$supporting_fields,$cm,$id,$formtype);

    //if the search form is cancelled, go to the view screen
    if ($mform->is_cancelled()) {
        $mode = 'view';

        dataplus_view_records();

        return;
    }

    $parameters = array();
    $order = array();

    //if a search form has been submitted...
    if ($form = $mform->get_data()) {
        $column_names = $dataplus_db->list_table_columns_names(true,$col_parameters);
        $date_columns = $dataplus_db->list_table_datetime_column_names();

        //build the parameters to be used in the SQL query
        foreach ($form as $name => $value) {
            if (in_array($name,$column_names) && $value !== '' && $value !== ' ' && $value !== 'null') {
                if (in_array($name,$date_columns)) {
                    if ($value == '-3600' || $value == '39600') {
                        continue;
                    }
                }
                
                $i = sizeof($parameters);
                $parameters[$i]->name     = $name;
                $parameters[$i]->value    = $value;

                $specificity_name = $name . '_specificity';

                if (isset($form->$specificity_name)) {
                    $parameters[$i]->operator = $form->$specificity_name;
                }

                $arrow_name = $name.'_arrow';

                if (isset($form->$arrow_name)) {
                    $parameters[$i]->operator = $form->$arrow_name;
                }
            }
        }

        $i = 0;
        $fname = 'sort' . $i;

        //build the sort if any is available or used.
        while (isset($form->$fname)) {
            if (!empty($form->$fname)) {
                $sort_name = 'sort_options' . $i;

                $order[$form->$fname]->name = $form->$fname;
                
                if (isset($form->$sort_name)) {
                    $order[$form->$fname]->sort = $form->$sort_name;
                }
            }

            $i++;
            $fname = 'sort'.$i;
        }

        //put all the search info in session for use if the search is amended.
        $SESSION->dataplus_search_parameters = $parameters;
        $SESSION->dataplus_search_order = $order;
        $SESSION->dataplus_formdata = $form;

        $mode = 'searchresults';

        //call the view screen.  The search parameters are used to query the database here
        dataplus_view_records(null, $parameters, $order);

        return;
    }

    //if no search has been submitted, display the search form.
    if ($mode == 'searchamend') {
        $mform->set_data($SESSION->dataplus_formdata);
    }

    dataplus_view_page_setup();
    $mform->display();
}


function dataplus_view_page_setup($js = null, $js_init = null, $css = null){
    global $mode, $COURSE, $dataplus, $context, $CFG, $cm;

    $view_label = (empty($dataplus->viewtablabel)) ? get_string('view','dataplus') : $dataplus->viewtablabel;

    dataplus_page_setup('/mod/dataplus/view.php',dataplus_get_querystring_vars(),$view_label, $js, $js_init, $css);

    $group = optional_param('group', null, PARAM_TEXT);
    $oldmode = optional_param('oldmode', 'view', PARAM_TEXT);  
    $editing_modes = dataplus_get_edit_modes();

 //if a user hasn't yet submitted enough records, don't let them view the database (as per mod settings)
    if ($mode != 'insertbelowlimit') {
        if ($mode == 'insert'  && is_null($group)) {
            $currenttab = 'insert';
        } else if((in_array($mode,dataplus_get_search_modes()) || in_array($oldmode,dataplus_get_search_modes())) && is_null($group)){
            $currenttab = 'search';
        } else if ($mode == 'single'){
            $currenttab = 'single';
        } else {
         $currenttab = $oldmode;
        }

        include('tabs.php');
    }
}


/**
 * generates screen for adding or editing a record and handles form submission
 */
function dataplus_amend_comment($rid){
    global $dataplus_db, $CFG, $id, $dataplus;

    //check to see if there is an id for a record to update (must have this for edit mode not to be ignored)
    $update_id = optional_param('cui', null, PARAM_INT);
    
    //add some parameters that will eventually be used in the update SQL
    if (!empty($update_id)) {
        $prev_result = $dataplus_db->get_comment($update_id);

        //if the current group doesn't have the right to alter the record, return
        if (!dataplus_check_groups($prev_result, true, true)) {
            return;
        }
    }

    require_once('comment_form.php');

    if(!is_null($update_id)){
        dataplus_log('update comment');
    } else {
        dataplus_log('insert comment');
    }

    $url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode=single&changecomment=".dataplus_get_comment_amend()."&ps=".dataplus_get_page_start();

    if (!is_null($update_id)) {
        $url .= "&cui=" . $update_id;
    }

    $url .= "#comments";

    $mform = new dataplus_comment_form($url);

    if ($mform->is_cancelled()) {
        return;
    }

    //handle form submission
    if ($form = $mform->get_data()) {
        //if there's an update id available, use the results array for an SQL update, otherwise and insert.
        if (!is_null($update_id)) {
            $dataplus_db->update_comment($update_id,undo_escaping($form->comment));
        } else {
            $dataplus_db->insert_comment($rid,undo_escaping($form->comment));
        }

        return;
    }

    //if editing, include previous values in the form.
    if (!is_null($update_id)) {
        $display_values['comment'] = $prev_result->comment;

        $mform->set_data($display_values);
    }

    $mform->display();
}


/**
 * generates the screen for deleting a record
 */
function dataplus_delete_comment($comment_template = NULL){
    global $dataplus_db, $CFG, $id, $SESSION, $mode;

    dataplus_log('delete comment');

    require_once('delete_comment_form.php');

    //get the id of the record to delete
    $update_id = required_param('cui', PARAM_INT);
    $url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&mode={$mode}&cui={$update_id}&ps=".dataplus_get_page_start()."&changecomment=".dataplus_get_comment_delete()."#comments";
    $mform = new dataplus_delete_comment_form($url);

    if (!is_null($comment_template)) {
        $mform->message = dataplus_print_template_comments_output($comment_template,NULL,NULL,NULL,$update_id,true);
    }

    $mform->define_fields();

    //if form submitted and cancelled, return to the view screen
    if ($mform->is_cancelled()) {
        return;
    }

    if ($form = $mform->get_data()) {
        $dataplus_db->delete_comment($update_id);
        return;
    }

    //get the record as it currently stands
    $parameters[0]->name = 'id';
    $parameters[0]->value = $update_id;
    $parameters[0]->operator ='equals';

    $prev_result = $dataplus_db->get_comment($update_id);

    //if the group does not have the rights to delete the record, return.
    if (!dataplus_check_groups($prev_result, true, true)) {
        return;
    }

   //if no form submission, then display the record to be deleted and the delete form.
    $mform->display();
}

dataplus_base_setup();

$group = optional_param('group', 0, PARAM_TEXT);

//according to the mode call the appropriate function, or display an error if the user doesn't have correct capabilities.
//empty($group) checks to prevent problems with interface when the group selected is changed.

$capability_check = (has_capability('mod/dataplus:dataeditown', $context) || has_capability('mod/dataplus:dataeditothers', $context));
$group_check = ($groupmode == 0 || groups_is_member($currentgroup));
$edit_check = has_capability('mod/dataplus:databaseedit', $context);

if (($mode == 'delete' || $mode == 'deletesubmit') && empty($group)) {
    if ($edit_check || ($group_check && $capability_check)) {
        dataplus_delete_record();
    } else {
        print_error(get_string('capablilty_delete_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
    }
} else if (in_array($mode,$editing_modes) && empty($group)) {
    if ($edit_check || ($group_check && $capability_check)) { 
        dataplus_amend_record();
    } else {
        print_error(get_string('capablilty_insert_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
    }
} else if(($mode == 'single') && empty($group)){
    if (has_capability('mod/dataplus:view', $context)) {
        dataplus_view_single_record();
    } else {
        print_error(get_string('capablilty_view_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);      
    }
} else if (in_array($mode,array('search','searchadvanced','searchamend')) && empty($group)) {
    if (has_capability('mod/dataplus:view', $context)) {
        dataplus_search_records();
    } else {
        print_error(get_string('capablilty_view_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);           
    }
} else if ($mode == 'searchresults' && empty($group)) {
    if(has_capability('mod/dataplus:view', $context)){
        $parameters = $SESSION->dataplus_search_parameters;
        $order = $SESSION->dataplus_search_order;

        dataplus_view_records(null,$parameters,$order);
    } else {
        print_error(get_string('capablilty_view_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);           
    }   
} else { 
    if (has_capability('mod/dataplus:view', $context)) {
        $mode = 'view';
        dataplus_view_records();
    } else {
        print_error(get_string('capablilty_view_database','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);           
    }
}

echo $OUTPUT->footer();
dataplus_base_close();