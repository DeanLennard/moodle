<?php 
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */


$capabilities = array(

    // Ability to view a database
    'mod/dataplus:view' => array(

        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW
        )
    ),

    // Ability to add / edit / delete one's own records in the database
    'mod/dataplus:dataeditown' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW
        )
    ),
    
    // Ability to add / edit / delete one's others records in the database
    'mod/dataplus:dataeditothers' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW
        )
    ),
    
        // Ability to alter the structure of a database
    'mod/dataplus:databaseedit' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW
        )
    ),
    
    
     // Ability to alter the structure of a database
    'mod/dataplus:downloadfull' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE
    )
);

