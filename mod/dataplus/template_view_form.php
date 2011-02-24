<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class dataplus_template_view_form extends moodleform {
    
    function definition() {
        global $CFG, $id, $SESSION, $dataplus_db, $mode, $dataplus;
        $optional_editor = $SESSION->dataplus_use_editor;

        $mform =&$this->_form;
        
        $cssfunction = "dataplusShowHideTemplateFormElement('id_css','dataplus_css_link','".get_string('css_show', 'dataplus')."','".get_string('css_hide', 'dataplus')."');";
        $jsfunction = "dataplusShowHideTemplateFormElement('id_javascript','dataplus_js_link','".get_string('javascript_show', 'dataplus')."','".get_string('javascript_hide', 'dataplus')."'); dataplusShowHideTemplateFormElement('id_javascript_init',false,false,false);";
        $resetlink = "window.location.href='{$CFG->wwwroot}/mod/dataplus/templates.php?id={$id}&amp;reset=true&amp;mode={$mode}'";
           
        if ($optional_editor == 'textarea') {
            $editor     = 'htmleditor';
            $editor_str = get_string('enable_editor', 'dataplus');
        } else {
            $editor = 'textarea';
            $editor_str = get_string('disable_editor', 'dataplus');
        }

        $editorlink  = "window.location.href='{$CFG->wwwroot}/mod/dataplus/templates.php?id={$id}&amp;editor={$editor}&amp;mode={$mode}'";

        $top_html = "
            <div class=\"dataplus_template_buttons\">
                <input type=\"button\" id=\"dataplus_css_link\" onclick=\"{$cssfunction}\" value=\"" . get_string('css_show', 'dataplus') . "\"/> 
                <input type=\"button\" id=\"dataplus_js_link\" onclick=\"{$jsfunction}\" value=\"" . get_string('javascript_show', 'dataplus') .  "\"/>
                <input type=\"button\" id=\"dataplus_reset_link\" onclick=\"{$resetlink}\" value=\"" . get_string('resettemplate', 'dataplus') . "\"/>                
                <input type=\"button\" id=\"dataplus_editor_link\" onclick=\"{$editorlink}\" value=\"" . $editor_str . "\"/> 
            </div>";

        $mform->addElement('html',$top_html);
        $mform->addElement('textarea', 'css', get_string('css', 'dataplus'),array('rows'=>25, 'cols' => '40'));
        $mform->addElement('textarea', 'javascript', get_string('javascript', 'dataplus'),array('rows'=>25, 'cols' => '40'));           
        $mform->addElement('textarea', 'javascript_init', get_string('javascript_init', 'dataplus'),array('rows'=>4, 'cols' => '40'));   
        $mform->addElement('static','headermenu','',dataplus_get_template_headerfooter_menu('header'));

        if ($mode != 'addrecord') {
            $mform->addElement($optional_editor, 'header', get_string('header', 'dataplus'),array('rows'=>12, 'cols' => '40'));
        }

        $mform->addElement('static','menu','',dataplus_get_template_record_menu($mode));

        $col_count = $dataplus_db->count_database_query("column");

        $mform->addElement($optional_editor, 'record', get_string('record', 'dataplus'),array('rows'=>$col_count + 15, 'cols' => '40', 'onclick'=>'datapluscursorPosition()', 'onkeyup'=>'datapluscursorPosition()'));

        if (dataplus_allow_comments()) {
            $mform->addElement('static','menu','',dataplus_get_template_comments_menu());
            $mform->addElement($optional_editor, 'comments', get_string('comments', 'dataplus'),array('rows'=>20, 'cols' => '40'));
        }

        if ($mode != 'addrecord') {
        $mform->addElement('static','footermenu','',dataplus_get_template_headerfooter_menu('footer'));
            $mform->addElement($optional_editor, 'footer', get_string('footer', 'dataplus'),array('rows'=>12, 'cols' => '40'));
        }

        if ($mode != 'addrecord') {
            $parameters = dataplus_get_restricted_groups_parameters();
            $columns = $dataplus_db->list_dataplus_table_columns_array(true,$parameters);
            $columns = array_merge(array('na'=>get_string('na', 'dataplus')),$columns);

            $mform->addElement('static', 'so', get_string('sortorder', 'dataplus'));

            for ($i=1; $i<=dataplus_sort_order_limit(); $i++) {
               $mform->addElement('select', 'sortorder'.$i, '', $columns);

                $sort_options = array();
                $sort_options[] = &MoodleQuickForm::createElement('radio', 'sortoption'.$i, '', get_string('ascending', 'dataplus'), 'ASC');
                $sort_options[] = &MoodleQuickForm::createElement('radio', 'sortoption'.$i, '', get_string('descending', 'dataplus'), 'DESC');
                $mform->addGroup($sort_options, 'sortoption' . $i, '', array(' '), false);
            }
        }

        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));
    }
}