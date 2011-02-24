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


/**
 * generates the screen for creating / editing a template
 */
function dataplus_manage_template(){
    global $dataplus_db, $CFG, $id, $currentgroup, $SESSION, $mode;

    require_once('template_view_form.php');

    $reset = optional_param('reset', null, PARAM_TEXT);

    if ($reset == 'true') {
        $dataplus_db->delete_template($mode);
    }

    $editor = optional_param('editor', null, PARAM_TEXT);

    if (!empty($editor)) {
        $SESSION->dataplus_use_editor = $editor;
    } else {
        $SESSION->dataplus_use_editor = 'textarea';
    }

    $mform = new dataplus_template_view_form("{$CFG->wwwroot}/mod/dataplus/templates.php?id={$id}&mode={$mode}");

    if ($form = $mform->get_data()) {
        if ($mode == 'addrecord') {
            $template_data = dataplus_resolve_addrecord_form_data($form);
        } else {
            $template_data = dataplus_resolve_form_data($form);
        }

        $dataplus_db->update_template($template_data);

        echo get_string('templateupdated', 'dataplus');
    }

    if ($mode == 'addrecord') {
        $form_data = dataplus_get_addrecord_form_values();
    } else {
        $form_data = dataplus_get_form_values();
    }

    $mform->set_data($form_data);

    echo '<div id="dataplus_templateform">';

    $mform->display();

    $editor_var = 'editor_' . md5('record');

    echo "<script type=\"text/javascript\" defer=\"defer\">
            if (typeof({$editor_var}) != 'undefined') {currEditor = {$editor_var}; }
                var ta = document.getElementById('id_record');
                var attr = document.createAttribute('onmouseup');
                ta.setAttributeNode(attr);
                ta.onmouseup = function(){datapluscursorPosition()}
                ta.onkeyup = function(){datapluscursorPosition()}
        </script>";

    echo '</div>';
}


/**
 * gets existing values for a template form
 */
function dataplus_get_form_values(){
    global $mode, $dataplus_db;

    $template = $dataplus_db->get_template($mode);
    $columns = $dataplus_db->list_dataplus_table_columns(true);
    $functions = dataplus_detail_supporting_functions();
    $actions = dataplus_detail_supporting_actions();
    $infos = dataplus_detail_supporting_record_information();

    $defaultvals = array();

    if (empty($template->record)) {
        $defaultvals = array('record' => dataplus_get_default_view_template());
    } else {
        foreach ($columns as $column) {
            $template->record = str_replace("[[{$column->name}]]","[[{$column->label}]]",$template->record);
            $template->record = str_replace("##{$column->name}##","##{$column->label}##",$template->record);
        }

        foreach ($actions as $action) {
            $template->record = str_replace("##{$action->name}##","##{$action->label}##",$template->record);
        }

        foreach ($infos as $i) {
            $template->record = str_replace("++{$i->name}++","++{$i->label}++",$template->record);
        }

        $defaultvals['record'] = $template->record;
    }

    if (empty($template->header)) {
        $defaultvals['header'] = dataplus_get_default_header();
    } else {
        $defaultvals['header'] = $template->header;
    }

    if (empty($template->footer)) {
        $defaultvals['footer'] = dataplus_get_default_footer();
    } else {
        $defaultvals['footer'] = $template->footer;
    }

    if (empty($template->comments)) {
        $defaultvals['comments'] = dataplus_get_default_comments();
    } else { 
        $defaultvals['comments'] = $template->comments;
    }

    foreach ($actions as $action) {
        $defaultvals['comments'] = str_replace("**{$action->name}**","**{$action->label}**",$defaultvals['comments']);
    }
        
    foreach ($functions as $function) {
        $defaultvals['header'] = str_replace("##{$function->name}##","##{$function->label}##",$defaultvals['header']);
        $defaultvals['footer'] = str_replace("##{$function->name}##","##{$function->label}##",$defaultvals['footer']); 
    } 

    if (empty($template->css)) {
        $defaultvals['css'] = dataplus_get_default_css();
    } else {
        $defaultvals['css'] = $template->css;
    }

    if (!empty($template->js)) {
        $defaultvals['javascript'] = $template->js;
    }

    if (!empty($template->js_init)) {
        $defaultvals['javascript_init'] = $template->js_init;
    }

    if (!empty($template->sortorder)) {
        $orders = explode(",",$template->sortorder);

        for ($i=0; $i < sizeof($orders); $i++) {
            $order_parts = explode(" ", $orders[$i]);
            $defaultvals['sortorder'.($i+1)] = $order_parts[0];

            if (sizeof($order_parts) == 2) {
                $defaultvals['sortoption'.($i+1)] = $order_parts[1];
            }
        }
    }

    return $defaultvals;
}


/**
 * get the existing values for an add record template form
 */
function dataplus_get_addrecord_form_values(){
    global $mode, $dataplus_db;

    $template = $dataplus_db->get_template($mode);
    $columns = $dataplus_db->list_dataplus_table_columns(true);

    $defaultvals = array();

    if (empty($template->record)) {
        $defaultvals = array('record' => dataplus_get_default_addrecord_template($mode));
    } else {
        foreach ($columns as $column) {
            $template->record = str_replace("[[{$column->name}]]","[[{$column->label}]]",$template->record);
        }

        $defaultvals['record'] = $template->record;
    }

    if (empty($template->css)) {
        $defaultvals['css'] = dataplus_get_default_addrecord_CSS();
    } else {
        $defaultvals['css'] = $template->css;
    }

    if (!empty($template->js)) {
        $defaultvals['javascript'] = $template->js;
    }

    if (!empty($template->js_init)) {
        $defaultvals['javascript_init'] = $template->js_init;
    }

    return $defaultvals;
}


/**
 * resolve data from a template form for storage in the database
 * @param object $form
 */
function dataplus_resolve_form_data($form){
    global $dataplus_db, $mode, $currentgroup;

    $results = array();
    $columns = $dataplus_db->list_dataplus_table_columns(true);
    $functions = dataplus_detail_supporting_functions();
    $actions = dataplus_detail_supporting_actions();
    $infos = dataplus_detail_supporting_record_information();

    foreach ($columns as $column) {
        $form->record = str_replace("[[{$column->label}]]","[[{$column->name}]]",$form->record);
        $form->record = str_replace("##{$column->label}##","##{$column->name}##",$form->record);
    }

    foreach ($functions as $function) {
        $form->header = str_replace("##{$function->label}##","##{$function->name}##",$form->header);
        $form->footer = str_replace("##{$function->label}##","##{$function->name}##",$form->footer);
    }

    foreach ($actions as $action) {
        $form->record = str_replace("**{$action->label}**","**{$action->name}**",$form->record);
    }

    foreach ($infos as $i) {
        $form->record = str_replace("++{$i->label}++","++{$i->name}++",$form->record);
    }

    $results[0]->name = 'css';
    $results[0]->value = undo_escaping($form->css);

    $results[1]->name = 'js';
    $results[1]->value = undo_escaping($form->javascript);

    $results[2]->name = 'js_init';
    $results[2]->value = undo_escaping($form->javascript_init);  
        
    $results[3]->name = 'header';
    $results[3]->value = undo_escaping($form->header);

    $results[4]->name = 'record';
    $results[4]->value = undo_escaping(str_replace('\"','"',$form->record));
        
    $results[5]->name = 'footer';
    $results[5]->value = undo_escaping($form->footer);

    $results[6]->name = 'type';
    $results[6]->value = $mode;

    $results[7]->name = 'group_id';
    $results[7]->value = $currentgroup;

    if (dataplus_allow_comments()) {
        $results[7]->name = 'comments';
        $results[7]->value = undo_escaping($form->comments);

        foreach ($actions as $action) {
            $form->comments = str_replace("**{$function->label}**","**{$function->name}**",$form->comments);
        }
    }

    $n = sizeof($results);
    $results[$n]->name  = 'sortorder';
    $results[$n]->value = dataplus_resolve_sort_order($form);

    return $results;
}


function dataplus_resolve_addrecord_form_data($form){
    global $dataplus_db, $mode, $currentgroup;

    $results = array();
    $columns = $dataplus_db->list_dataplus_table_columns(true);

    foreach ($columns as $column) {
        $form->record = str_replace("[[{$column->label}]]","[[{$column->name}]]",$form->record);
    }

    $results[0]->name = 'css';
    $results[0]->value = undo_escaping($form->css);

    $results[1]->name = 'js';
    $results[1]->value = undo_escaping($form->javascript);

    $results[2]->name = 'js_init';
    $results[2]->value = undo_escaping($form->javascript_init);

    $results[3]->name = 'record';
    $results[3]->value = undo_escaping(str_replace('\"','"',$form->record));

    $results[5]->name = 'type';
    $results[5]->value = $mode;
                
    $results[6]->name = 'group_id';
    $results[6]->value = $currentgroup;

    return $results;
}


function dataplus_resolve_sort_order($form){
    $sortorder = '';

    for ($i = 1; $i <= dataplus_sort_order_limit(); $i++) {
        $sortorder_name = "sortorder" . $i;
        $sortoption_name = "sortoption" . $i;
            
        if ($form->$sortorder_name != 'na') {
            if (strlen($sortorder)>0) {
                $sortorder .= ',';
            }

            $sortorder .= $form->$sortorder_name;

            if (isset($form->$sortoption_name)) {
                if ($form->$sortoption_name == 'DESC') {
                    $sortorder .= ' DESC';
                } else {
                    $sortorder .= ' ASC';
                }
            }
        }
    }

    return $sortorder;
}

dataplus_base_setup(); 
dataplus_page_setup('/mod/dataplus/templates.php',dataplus_get_querystring_vars(),get_string('templates','dataplus'),'/mod/dataplus/template_js_form.js');

$currenttab = 'templates';

if (empty($mode)) {
    $mode = 'view';
}

include('tabs.php');

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    dataplus_manage_template();
} else {
    print_error('capablilty_edit_template','dataplus', $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
}

echo $OUTPUT->footer();

dataplus_base_close();