<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
	
//form for importing a Dataplus database.
class dataplus_import_form extends moodleform {
		
    function definition(){
        $mform = &$this->_form;
	
        $mform->addElement('header', 'general', get_string('importdb', 'dataplus'));

        $mform->addElement('static','warning','',get_string('importwarning', 'dataplus'));	

        $mform->addElement('file', 'importfile', get_string('database', 'dataplus'));

        $mform->addElement('checkbox','remgroups', get_string('importremovegroup', 'dataplus'));

        $mform->setDefault('remgroups', 1);

        $this->add_action_buttons(true, get_string('import','dataplus'));
    }
}