<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

// form for searching the database
class dataplus_search_form extends moodleform {

    /**
     * moodleforms requires a definition(), but I don't want the fields defined when the 
     * class is instantiated, so this one does nothing
     */
    function definition() {
        return;
    }

    //three functions for defining types of form fields that are used more than once

    /**
     * sort fields for the advanced search form
     *
     * @param obj $mform
     * @param int $no
     * @param array $sort_choices
     */
    function define_sort_field($mform,$no,$sort_choices){
        $mform->addElement('select', 'sort' . $no, get_string('sort' . ($no+1), 'dataplus'), $sort_choices);

        $sort_options   = array();
        $sort_options[] = &MoodleQuickForm::createElement('radio', 'sort_options' . $no, '', get_string('ascending', 'dataplus'), 'ASC');
        $sort_options[] = &MoodleQuickForm::createElement('radio', 'sort_options' . $no, '', get_string('descending', 'dataplus'), 'DESC');
        $mform->addGroup($sort_options, 'sort_options' . $no, '', array(' '), false);
        //$mform->setDefault('sort_options' . $no, 'ASC');
    }


    /**
     * before, equal or since for date fields on the advanced form
     *
     * @param obj $field
     */
    function define_date_options($field){
        $date_options   = array();
        $date_options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('before', 'dataplus'), 'lessthan');
        $date_options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('equals', 'dataplus'), 'equals');
        $date_options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('since', 'dataplus'), 'greaterthan');

        return $date_options;
    }


    /**
     * add a text field, with advanced search options if required.
     *
     * @param obj $mform
     * @param string $formtype
     * @param string $name
     * @param string $label
     */
    function define_text_field($mform,$formtype,$name,$label){
        $str_contains = get_string('contains', 'dataplus');
        $str_equals   = get_string('equals', 'dataplus');

        $mform->addElement('text', $name, format_string($label));

        if($formtype == 'searchadvanced'){
            $speficity_options   = array();
            $speficity_options[] = &MoodleQuickForm::createElement('radio', $name . '_specificity', '', $str_contains, 'contains');
            $speficity_options[] = &MoodleQuickForm::createElement('radio', $name . '_specificity', '', $str_equals, 'equals');
            $mform->addGroup($speficity_options, $name .  '_specificity', '', array(' '), false);
            //$mform->setDefault($name .  '_specificity', 'contains');
        }
    }


    /**
     * this functions actually defines the search form
     *
     * @param array $fields
     * @param array $supporting_fields
     * @param obj $cm
     * @param int $id
     * @param string $formtype
     */
    function define_fields($fields,$supporting_fields,$cm,$id,$formtype = 'search'){
        global $CFG, $dataplus_db;

        $mform =&$this->_form;

        $mform->addElement('header', 'general', get_string('search', 'dataplus'));

        $search_url = "{$CFG->wwwroot}/mod/dataplus/view.php?id={$id}&amp;mode=";

        //if on an advanced search, add a link to simple search and vice versa
        if($formtype=='searchadvanced'){
            $str = get_string('simplesearch', 'dataplus');
            $mform->addElement('static','link','',"<div class=\"dataplus_search_type\"><a href=\"{$search_url}search\">{$str}</a></div>");
        }
        else{
            $str = get_string('advancedsearch', 'dataplus');
            $mform->addElement('static','link','',"<div class=\"dataplus_search_type\"><a href=\"{$search_url}searchadvanced\">{$str}</a></div>");
        }

        $text_search_types = array('smalltext','url','longtext','image');

        $sort_choices = array (''=>'');

        //itterate through each field and display according to the form_field_type
        foreach($fields as $field){
            if(in_array($field->form_field_type,$text_search_types)){
                if($field->form_field_type == 'image'){
                    $name  = $field->name . $dataplus_db->get_supporting_suffix();
                    $label = $field->label . " " . get_string('alttag', 'dataplus');
                }
                else {
                    $name = $field->name;
                    $label = $field->label;
                }

                $this->define_text_field($mform,$formtype,$name,$label);

                if($field->form_field_type == 'url' && $formtype == 'searchadvanced'){
                    $this->define_text_field($mform,$formtype,$name . $dataplus_db->get_supporting_suffix(),get_string('suppdesc','dataplus',$field->label));
                }
            }
            else if ($field->form_field_type == 'number'){
                $mform->addElement('text', $field->name, format_string($field->label));

                if($formtype == 'searchadvanced'){
                    $options   = array();
                    $options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('lessthan', 'dataplus'), 'lessthan');
                    $options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('equals', 'dataplus'), 'equals');
                    $options[] = &MoodleQuickForm::createElement('radio', $field->name . '_arrow', '', get_string('greaterthan', 'dataplus'), 'greaterthan');

                    $mform->addGroup($options, $field->name . '_arrow', '', array(' '), false);
                    //$mform->setDefault($field->name . '_arrow', 'equals');
                }
            }
            else if ($field->form_field_type == 'date' || $field->form_field_type == 'datetime'){
                if($field->form_field_type == 'datetime'){
                    $type = 'date_time_selector';
                }
                else {
                    $type = 'date_selector';
                }

                $mform->addElement($type, $field->name, format_string($field->label));
                $mform->setDefault($field->name,39600);

                $date_options = $this->define_date_options($field);
                $mform->addGroup($date_options, $field->name . '_arrow', '', array(' '), false);
                $mform->setDefault($field->name . '_arrow', 'greaterthan');
            }
            else if($field->form_field_type == 'boolean'){
                $radioarray   = array();
                $radioarray[] = &MoodleQuickForm::createElement('radio', $field->name, '', get_string('true', 'dataplus'), 1);
                $radioarray[] = &MoodleQuickForm::createElement('radio', $field->name, '', get_string('false', 'dataplus'), 0);
                $radioarray[] = &MoodleQuickForm::createElement('radio', $field->name, '', get_string('boo_ignore', 'dataplus'), 'null');
                $mform->addGroup($radioarray, $field->name, $field->label, array(''), false);
                $mform->setDefault($field->name, 'null');                   
            }
            else if($field->form_field_type == 'menusingle' || $field->form_field_type == 'menumultiple'){
                $options = array();

                $field_options = explode("\r\n",$field->form_field_options);

                if(sizeof($field_options) <= 1){
                    $field_options = explode("\r",$field->form_field_options);
                }

                if(sizeof($field_options) <= 1){
                    $field_options = explode("\n",$field->form_field_options);
                }

                if(!empty($field_options[0])){
                    $field_options = array_merge(array(' '),$field_options);
                }
                    
                foreach($field_options as $field_option){
                    $options[$field_option] = $field_option;
                }
                    
                $select = $mform->addElement('select', $field->name, format_string($field->label), $options);
            }
            else {
                continue;
            }

            if($formtype == 'searchadvanced'){
                $sort_choices[$field->name] = $field->label;
            }
        }

        if($formtype == 'searchadvanced'){
            foreach ($supporting_fields as $field){
                if(!$field->hidden){
                    if($field->type == 'text'){
                        $mform->addElement('static','break','','<br/>');
                        $mform->addElement('text', $field->name, format_string($field->label));
                    }
                    else if($field->type == 'date'){
                        $mform->addElement('date_selector', $field->name, format_string($field->label));
                        $mform->setDefault($field->name,$cm->added);

                        $date_options = $this->define_date_options($field);
                        $mform->addGroup($date_options, $field->name . '_arrow', '', array(' '), false);
                        $mform->setDefault($field->name . '_arrow', 'greaterthan');
                    }

                    $sort_choices[$field->name] = $field->label;
                }
            }

            $mform->addElement('static','break','','<br/><strong>' . get_string('sort', 'dataplus') . '</strong>');

            if((sizeof($sort_choices)-1)<3){
                $levels = sizeof($sort_choices)-1;
            }
            else {
                $levels = 3;
            }

            for($i=0; $i<$levels; $i++){
                $this->define_sort_field($mform,$i,$sort_choices);
            }
        }

        $this->add_action_buttons(true,get_string('search', 'dataplus'));
    }
}