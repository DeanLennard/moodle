<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */
function dataplus_restore_mods($mod,$restore) {
    global $CFG;

	$status = true;
	
	$dataplus = backup_getid($restore->backup_unique_code,$mod->modtype,$mod->id);
	    
	if ($dataplus) {
	    $info = $dataplus->info;
	    	   
	    if ($restore->course_startdateoffset) {
            restore_log_date_changes('Database', $restore, $info['MOD']['#'], array('TIMEAVAILABLEFROM', 'TIMEAVAILABLETO','TIMEVIEWFROM', 'TIMEVIEWTO'));
        }
        	
        $database->course = $restore->course_id;       	

	    $database->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
	    $database->intro = backup_todb($info['MOD']['#']['INTRO']['0']['#']);
	    $database->timeavailablefrom = backup_todb($info['MOD']['#']['TIMEAVAILABLEFROM']['0']['#']);
	    $database->timeavailableto = backup_todb($info['MOD']['#']['TIMEAVAILABLETO']['0']['#']);
	    $database->timeviewfrom = backup_todb($info['MOD']['#']['TIMEVIEWFROM']['0']['#']);
	    $database->timeviewto = backup_todb($info['MOD']['#']['TIMEVIEWTO']['0']['#']);
	    $database->requiredentries = backup_todb($info['MOD']['#']['REQUIREDENTRIES']['0']['#']);
	    $database->requiredentriestoview = backup_todb($info['MOD']['#']['REQUIREDENTRIESTOVIEW']['0']['#']);
	    $database->maxentries = backup_todb($info['MOD']['#']['MAXENTRIES']['0']['#']);
	    $database->maxentriesperuser = backup_todb($info['MOD']['#']['MAXENTRIESPERUSER']['0']['#']);
	    $database->studentediting = backup_todb($info['MOD']['#']['STUDENTEDITING']['0']['#']);
	    $database->studenteditingother = backup_todb($info['MOD']['#']['STUDENTEDITINGOTHER']['0']['#']);
	    $database->teacherediting = backup_todb($info['MOD']['#']['TEACHEREDITING']['0']['#']);
	    $database->teachereditingother = backup_todb($info['MOD']['#']['TEACHEREDITINGOTHER']['0']['#']);
        $database->viewtablabel = backup_todb($info['MOD']['#']['VIEWTABLABEL']['0']['#']);
        $database->viewtabvisible = backup_todb($info['MOD']['#']['VIEWTABVISIBLE']['0']['#']);            
        $database->singlerecordtablabel = backup_todb($info['MOD']['#']['SINGLERECORDTABLABEL']['0']['#']);
        $database->singlerecordtabvisible = backup_todb($info['MOD']['#']['SINGLERECORDTABVISIBLE']['0']['#']);              
        $database->searchtablabel = backup_todb($info['MOD']['#']['SEARCHTABLABEL']['0']['#']);
        $database->searchtabvisible = backup_todb($info['MOD']['#']['SEARCHTABVISIBLE']['0']['#']);
        $database->addrecordtablabel = backup_todb($info['MOD']['#']['ADDRECORDTABLABEL']['0']['#']);
        $database->addrecordtabvisible = backup_todb($info['MOD']['#']['ADDRECORDTABVISIBLE']['0']['#']);
        $database->exporttablabel = backup_todb($info['MOD']['#']['EXPORTTABLABEL']['0']['#']);
        $database->exporttabvisible = backup_todb($info['MOD']['#']['EXPORTTABVISIBLE']['0']['#']);                                                                   	    	
	    $database->savebuttonlabel = backup_todb($info['MOD']['#']['SAVEBUTTONLABEL']['0']['#']);
	    $database->navigationlimit = backup_todb($info['MOD']['#']['NAVIGATIONLIMIT']['0']['#']);
	    $database->listperpage = backup_todb($info['MOD']['#']['LISTPERPAGE']['0']['#']);
	    $database->rssarticles = backup_todb($info['MOD']['#']['RSSARTICLES']['0']['#']);
	    	
	    $newid = $DB->insert_record ('dataplus', $database);
	    	
	    if(!$newid){
	    	return false;
	    }

	    backup_putid($restore->backup_unique_code,$mod->modtype, $mod->id, $newid);
	    	
	    if (!defined('RESTORE_SILENTLY')) {
            echo "<li>".get_string("modulename","dataplus")." \"".format_string(stripslashes($database->name),true)."\"</li>";
        }

        return dataplus_restore_files($newid,$mod->id,$restore);
	}
	else {
	    return false;
	}
}
	
	
function dataplus_restore_files($newid,$oldid,$restore){
	global $CFG;
		
	require_once($CFG->dirroot . '/mod/dataplus/dataplus_file_helper.php');
		
	$dataplus_file_helper = new dataplus_file_helper($newid);
		
	$backup_path = $CFG->dataroot . "/temp/backup/" . $restore->backup_unique_code . "/moddata/dataplus/" . $oldid;
	$new_path    = $dataplus_file_helper->get_path();
		
	$result = $dataplus_file_helper->copy_dir($backup_path, $new_path);
		
	if($result !== true){
		return false;
	}
			
	$rn = rename($new_path . '/' . $oldid . '.sqlite', $new_path . '/' . $newid . '.sqlite');
			
	if($rn !== true){
		return $rn;
	}
			
	require_once($CFG->dirroot . '/mod/dataplus/sqlite3_db_dataplus.php');
			
	$dataplus_db = new sqlite3_db_dataplus($newid,$new_path);
			
    $group_param[0]->name     = 'group_id';
    $group_param[0]->value    = '0';
    $group_param[0]->operator = 'notequal';
            
    $group_param[1]->name     = 'group_id';
    $group_param[1]->value    = '';
    $group_param[1]->operator = 'notequal';
			
	$cols = $dataplus_db->list_dataplus_table_columns(true,$group_param);
			
	if(dataplus_restore_groups($cols,"column",$restore,$dataplus_db) !== true){
		return false;
	}
			
	$content = $dataplus_db->query_dataplus_database(array('group_id'),$group_param);
			
	if(dataplus_restore_groups($content,"content",$restore,$dataplus_db) !== true){
        return false;
    }
            
    $templates = $dataplus_db->get_templates();
            
	if(dataplus_restore_groups($templates,"templates",$restore,$dataplus_db) !== true){
        return false;
    }

    return true;
}

		
function dataplus_restore_groups($records,$table,$restore,$dataplus_db){
    $groups = array();
            
    foreach($records as $record){
        if(!in_array($record->group_id,$groups)){
            $groups[] = $record->group_id;
        }
    }

    foreach($groups as $group){
	    $new_info = restore_group_getid($restore, $group);

	    if(!$new_info){
	    	continue;
	    }
	    
        $columns[0]->name  = 'group_id';
        $columns[0]->value = $new_info->new_id;     

        $parameters[0]->name     = 'group_id';
        $parameters[0]->value    = $group;
        $parameters[0]->operator = 'equals';
                
        $result = $dataplus_db->update_record($table,$columns,$parameters);
            
        if($result !== true){
            return $result;
        }
    }
        
    return true;
}