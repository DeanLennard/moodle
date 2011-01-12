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
class dataplus_delete_form extends moodleform {

    function definition() {

        $mform =&$this->_form;

        $this->add_action_buttons(true, get_string('confirmdelete', 'dataplus'));
  		
    }

}