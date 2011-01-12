<?php 
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

function xmldb_dataplus_upgrade($oldversion=0) {
	global $DB;

	$result = true;
	
	$dbman = $DB->get_manager();
	
    if ($result && $oldversion < 2010012500) {

    /// Define field format to be added to data_comments
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('navigationlimit');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'savebuttonlabel');

    /// Launch add field format
        $result = $result && $dbman->add_field($table, $field);

    }
    
    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('viewtablabel');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'teachereditingother');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    
    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('viewtabvisible');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'viewtablabel');

        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('singlerecordtablabel');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'viewtabvisible');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    
    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('singlerecordtabvisible');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'singlerecordtablabel');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('searchtablabel');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'singlerecordtabvisible');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    
    if ($result && $oldversion < 2010012501) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('searchtabvisible');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'searchtablabel');

        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2010012502) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('addrecordtablabel');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'searchtabvisible');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    
    if ($result && $oldversion < 2010012502) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('addrecordtabvisible');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'addrecordtablabel');

        $result = $result && $dbman->add_field($table, $field);
    }  

    if ($result && $oldversion < 2010012502) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('exporttablabel');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'addrecordtabvisible');

        $result = $result && $dbman->add_field($table, $field);
    }
    
    
    if ($result && $oldversion < 2010012502) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('exporttabvisible');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'exporttablabel');

        $result = $result && $dbman->add_field($table, $field);
    }      
    
    if ($result && $oldversion < 2010102902) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('maxentriesperuser');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '0', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '15', 'maxentriesperuser');

        $result = $result && $dbman->add_field($table, $field);
    }  
    
    
    if ($result && $oldversion < 2010121401) {
        $table = new xmldb_table('dataplus');
        $field = new xmldb_field('allowcomments');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'allowcomments');
        
        $result = $result && $dbman->add_field($table, $field);
    }  
    
    
    return $result;
}
