<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

require_once("../../config.php");
require_once("lib.php");
	
/**
 * Generate the export screen
*/
	
function dataplus_export(){
    global $dataplus_db, $CFG, $id, $context;
		
    $str_head = get_string('exportdb','dataplus');
		
    echo "<h2>$str_head</h2>";
		
    $url = "{$CFG->wwwroot}/mod/dataplus/export_download.php?id={$id}&amp;mode=";
		
    $str_download  = get_string('downloaddb','dataplus');
    $str_downloadh = get_string('downloaddbhelp','dataplus');
		
    echo "<p><a href=\"{$url}simple\">{$str_download}</a><br/>{$str_downloadh}</p>";
		
    $str_download_csv  = get_string('downloadcsv','dataplus');
    $str_download_csvh = get_string('downloadcsvhelp','dataplus');		
		
    echo "<p><a href=\"{$url}csv\">{$str_download_csv}</a><br/>{$str_download_csvh}</p>";
		
    if (has_capability('mod/dataplus:downloadfull', $context)) {
			
        $complex_url = "{$CFG->wwwroot}/mod/dataplus/export_download.php?id={$id}&amp;mode=complex";
					
        $str_downloadfull  = get_string('downloadfulldb','dataplus');
        $str_downloadfullh = get_string('downloadfulldbhelp','dataplus');			
			
        echo "<p><a href=\"{$complex_url}\">{$str_downloadfull}</a><br/>{$str_downloadfullh}</p>";
		
    }
		
}

dataplus_base_setup();

dataplus_page_setup('/mod/dataplus/export.php',dataplus_get_querystring_vars(),(empty($dataplus->exporttablabel)) ? get_string('export','dataplus') : $dataplus->exporttablabel);

$currenttab = 'export';

include('tabs.php');
    
if (has_capability('mod/dataplus:view', $context)) {
    dataplus_export();    	
}
else {
    print_error('capablilty_edit_template','dataplus', $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
}

echo $OUTPUT->footer();
dataplus_base_close();