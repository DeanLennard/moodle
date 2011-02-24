<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

// This file to be included so we can assume config.php has already been included.
// We also assume that $user, $course, $currenttab have been set

if (empty($currenttab) or empty($dataplus) or empty($COURSE)) {
    print_error('You cannot call this script in that way');
}

$row  = array();
$tabs = array();

global $groupmode, $currentgroup;

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    $row[] = new tabobject('manage', $CFG->wwwroot.'/mod/dataplus/manage.php?id='.$cm->id, get_string('managedatabase','dataplus'));
}

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    $row[] = new tabobject('templates', $CFG->wwwroot.'/mod/dataplus/templates.php?id='.$cm->id, get_string('templates','dataplus'));
}
   
if (has_capability('mod/dataplus:view', $context)) {
    if ($dataplus->viewtabvisible !== '0') {
        $row[] = new tabobject('view', $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id='.$cm->id, (empty($dataplus->viewtablabel)) ? get_string('view','dataplus') : $dataplus->viewtablabel);
    }

    if ($dataplus->singlerecordtabvisible !== '0') {
        $row[] = new tabobject('single', $CFG->wwwroot.'/mod/dataplus/view.php?mode=single&amp;id='.$cm->id, (empty($dataplus->singlerecordtablabel)) ? get_string('single_record','dataplus') : $dataplus->singlerecordtablabel);
    }

    if ($dataplus->searchtabvisible !== '0') {
        $row[] = new tabobject('search', $CFG->wwwroot.'/mod/dataplus/view.php?id='.$cm->id.'&amp;mode=search', (empty($dataplus->searchtablabel)) ? get_string('search','dataplus') : $dataplus->searchtablabel);
    }
}

$capabiliy_check = (has_capability('mod/dataplus:databaseedit', $context) || (has_capability('mod/dataplus:dataeditown', $context)));
$group_check = (($groupmode>0 && groups_is_member($currentgroup)) || empty($groupmode));

if ($dataplus->addrecordtabvisible !== '0' && isloggedin() && $capabiliy_check && $group_check) {
    $row[] = new tabobject('insert', $CFG->wwwroot.'/mod/dataplus/view.php?id='.$cm->id.'&amp;mode=insert', (empty($dataplus->addrecordtablabel)) ? get_string('addrecord','dataplus') : $dataplus->addrecordtablabel);
}

if ($dataplus->exporttabvisible !== '0' && has_capability('mod/dataplus:view', $context)) {
    $export_label = (empty($dataplus->exporttablabel)) ? get_string('export','dataplus') : $dataplus->exporttablabel;
    $row[] = new tabobject('export', $CFG->wwwroot.'/mod/dataplus/export.php?id='.$cm->id, $export_label);
}

if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
    $row[] = new tabobject('import', $CFG->wwwroot.'/mod/dataplus/import.php?id='.$cm->id, get_string('import','dataplus'));
}

$tabs[] = $row;

$activetwo = null;

if ($currenttab == 'templates') {
    $inactive = array();
    $inactive[] = 'templates';
    $templatelist = array ('view', 'single', 'addrecord');

    $row = array();
    $currenttab ='';

    foreach ($templatelist as $template) {
        $tab_name = 'template_'.$template;
        $row[] = new tabobject($tab_name, "templates.php?id=$id&amp;mode=$template", get_string('template_' . $template, 'dataplus'));

        if ($template == $mode) {
            $currenttab = $tab_name;
        }
    }

    if ($currenttab == '') {
        $currenttab = 'template_view';
    }

    $tabs[] = $row;
    $activetwo = array('templates');
}

print_tabs($tabs, $currenttab, null, $activetwo);