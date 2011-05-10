<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

//form for importing a Dataplus database.
class dataplus_import_form extends moodleform {

    public function definition() {
        $mform = &$this->_form;
        $mform->addElement('header', 'general', get_string('importdb', 'dataplus'));
        $mform->addElement('static', 'warning', '', get_string('importwarning', 'dataplus'));
        $mform->addElement('file', 'importfile', get_string('database', 'dataplus'));
        $mform->addElement('checkbox', 'remgroups', get_string('importremovegroup', 'dataplus'));
        $mform->setDefault('remgroups', 1);

        $this->add_action_buttons(true, get_string('import', 'dataplus'));
    }
}