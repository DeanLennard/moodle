<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

//form containing action buttons for confirming deletion
class dataplus_delete_comment_form extends moodleform {
	
	public $message = NULL;

    function definition() {
    	return;
    }
    
    function define_fields(){
        $mform =&$this->_form;
        $mform->addElement('html','<a name="deletecomment"></a>');
        $mform->addElement('html','<h2>'.get_string('deletecomment', 'dataplus').'</h2>');
        
        if(!is_null($this->message)){
        	$mform->addElement('html',$this->message);
        }

        $this->add_action_buttons(true, get_string('confirmdelete', 'dataplus'));
    }
}