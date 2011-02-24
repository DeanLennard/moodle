<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
	
class dataplus_manage_form extends moodleform {
	
/**
 * moodleforms requires a definition(), but I don't want the fields defined when the 
 * class is instantiated, so this one does nothing
 */
    function definition() {
        return;
    }

   /**
    * this function actually defines the form fields 
    *
    * @param int $itterations - the number of fields that can be created from this instance of a form
    * @param string $form_context - create a form to 'add' fields or 'edit' fields
    */
    function define_fields($itterations,$form_context = 'add'){
        global $dataplus_db, $groupmode, $cm, $currentgroup;

        $mform =&$this->_form;

        if ($form_context == 'edit') {
            $mform->addElement('header', 'general', get_string('editfield', 'dataplus'));
        } else {
            $mform->addElement('header', 'general', get_string('addfields', 'dataplus'));
        }

        for ($i = 0; $i < $itterations; $i++) {
            $mform->addElement('static','br' . $i,'','<br/>');
            $mform->addElement('text', 'fieldname' .  $i, get_string('fieldname','dataplus'), array('size'=>'64'));

            $options = $dataplus_db->get_field_types();

            unset($options['menusingle']);
            unset($options['menumultiple']);
            
            $options['menu'] = get_string('field_menu', 'dataplus');

            $mform->addElement('select', 'fieldtype' .  $i, get_string('fieldtype', 'dataplus'), $options);
            $mform->addElement('checkbox', 'fieldmultiple' .  $i, get_string('allowmultiple', 'dataplus'));
            $mform->disabledIf('fieldmultiple' .  $i, 'fieldtype' .  $i, '', 'menu');
            $mform->addElement('textarea', 'fieldoptions' .  $i, get_string('options', 'dataplus'), 'rows="5" cols="40"');
            $mform->disabledIf('fieldoptions' .  $i, 'fieldtype' .  $i, '', 'menu');

            if ($groupmode > 0) {
                $groups["0"] = get_string('allparticipants');
                $groups_data = groups_get_all_groups($cm->course, 0, $cm->groupingid);

                if ($groups_data !== false) {
                    foreach ($groups_data as $gd) {
                        $groups["{$gd->id}"] = $gd->name;
                    }

                    $mform->addElement('select','group_id' . $i,get_string('group','dataplus'),$groups);
                    $mform->setDefault('group_id' . $i,$currentgroup);
                }
            }
        }

        $mform->addElement('static','brend','','<br/>');
        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));
    }
}