<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */
require_once ($CFG->dirroot.'/course/moodleform_mod.php');
	
//form for adding or editing records
class dataplus_manage_form extends moodleform {

	/**
     * moodleforms requires a definition(), but I don't want the fields defined when the 
     * class is instantiated, so this one does nothing
     */
    function definition(){
        return;
    }


    /**
     * this really defines the fields
     *
     * @param array $fields - fields to be included on the form.
     * @param string $mode - to 'add' or 'edit' a record
     * @param obj $result - if editing, the result object for use here
     */
    function define_fields($fields,$mode,$template,$result = null){
        global $dataplus_db, $dataplus_filehelper, $dataplus, $groupmode, $currentgroup, $cm, $context;

        $mform =&$this->_form;

        $template_sections = explode(']]',$template);

        foreach ($template_sections as $ts ){
            $ts_eles = explode('[[',$ts);

            if(strpos($ts_eles[0],'**addcancel**') === false && !empty($ts_eles[0])) {
                $mform->addElement('html',$ts_eles[0]);
            } else if (!empty($ts_eles[0])) {
                $ac_spl = explode('**addcancel**',$ts_eles[0]);

                for ($i = 0; $i < (sizeof($ac_spl)-1); $i++) {
                    $mform->addElement('html',$ac_spl[$i]);

                    if ($groupmode > 0 && has_capability('mod/dataplus:databaseedit', $context)) {
                        $groups["0"] = get_string('allparticipants'); 
                        $groups_data = groups_get_all_groups($cm->course, 0, $cm->groupingid);

                        if ($groups_data !== false) {
                            foreach ($groups_data as $gd) {
                                $groups["{$gd->id}"] = $gd->name;
                            }

                            $mform->addElement('select','group_id',get_string('group','dataplus'),$groups);
                            $mform->setDefault('group_id',$currentgroup);
                        }
                    }

                    if ($mode == 'edit') {
                        $this->add_action_buttons(true);
                    } else {
                        $this->add_action_buttons(true,$dataplus->savebuttonlabel);
                    }
                }
                $mform->addElement('html',$ac_spl[sizeof($ac_spl)-1]);
            }

            if (!isset($ts_eles[1])) {
                continue;
            }

            $found = false;

            foreach ($fields as $field) {
                if ($field->name == $ts_eles[1]) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                continue;
            }

            if ($field->form_field_type == 'smalltext' || $field->form_field_type == 'number') {
                $mform->addElement('text', $field->name, format_string($field->label));
            } else if ($field->form_field_type == 'longtext') {
                $mform->addElement('htmleditor', $field->name, format_string($field->label));
            } else if($field->form_field_type == 'date') {
                $mform->addElement('date_selector', $field->name, format_string($field->label));
            } else if ($field->form_field_type == 'datetime') {
                $mform->addElement('date_time_selector', $field->name, format_string($field->label));
            } else if ($field->form_field_type == 'image' || $field->form_field_type == 'file') {
                if (!empty($result)) {
                    foreach ($result as $name=>$value) {
                        if ($name == $field->name) {
                            if ($field->form_field_type == 'image') {
                                $path = $dataplus_filehelper->get_image_file_path($value);
                                $alt_name = $field->name.'000alt';
                                $alt = null;

                                foreach ($result as $n=>$v) {
                                   if ($n == $alt_name) {
                                        $alt = $v;
                                        break;
                                    }
                                }

                                $html = "<img src=\"{$path}\" alt=\"{$alt}\"/>";

                                $mform->addElement('static','image' . $field->name,'',$html);
                                break;
                            } else if($field->form_field_type == 'file'){ 
                                $path = $dataplus_filehelper->get_file_file_path($value);
                                $html = "<a href=\"{$path}\">{$value}</a>";
                                $mform->addElement('static','file' . $field->name,'',$html);
                            }
                        }
                    }
                }

                $mform->addElement('filepicker', $field->name, format_string($field->label));

                if ($field->form_field_type == 'image') {
                    $mform->addElement('text', $field->name.'alt'.$dataplus_db->get_supporting_suffix(), get_string('suppdesc','dataplus',$field->label));
                }
            } else if ($field->form_field_type == 'url') {
                $mform->addElement('text', $field->name, format_string($field->label));
                $mform->addElement('text', $field->name.'desc'.$dataplus_db->get_supporting_suffix(), get_string('suppdesc','dataplus',$field->label));
            } else if($field->form_field_type == 'boolean'){
                $radioarray   = array();
                $radioarray[] = &MoodleQuickForm::createElement('radio', $field->name, '', get_string('true', 'dataplus'), 1);
                $radioarray[] = &MoodleQuickForm::createElement('radio', $field->name, '', get_string('false', 'dataplus'), 0);
                $mform->addGroup($radioarray, $field->name, $field->label, array(' '), false);
                $mform->setDefault($field->name, 2);
            } else if($field->form_field_type == 'menusingle' || $field->form_field_type == 'menumultiple'){
                $options = array();
                $field_options = explode("\r\n",$field->form_field_options);

                if (sizeof($field_options) <= 1) {
                    $field_options = explode("\r",$field->form_field_options);
                }

                if (sizeof($field_options) <= 1) {
                    $field_options = explode("\n",$field->form_field_options);
                }

                foreach ($field_options as $field_option) {
                    $options[$field_option] = $field_option;
                }

                $select = $mform->addElement('select', $field->name, format_string($field->label), $options);

                if ($field->form_field_type == 'menumultiple') {
                    $select->setMultiple(true);
                }
            }
        }
    } 
}