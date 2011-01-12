<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */

//This php script contains all the stuff to backup/restore dataplus mod
//
//Backs up the dataplus table data and the structure of the database as well as, optionally, user data.


function dataplus_backup_mods($bf,$preferences) {
      
    global $CFG;

    $status = true;

    //Iterate over dataplus table
    $datapluss = $DB->get_records ("dataplus","course",$preferences->backup_course,"id");
    
    if ($datapluss) {
        foreach ($datapluss as $dataplus) {
            if (backup_mod_selected($preferences,'dataplus',$dataplus->id)) {
                $status = dataplus_backup_one_mod($bf,$preferences,$dataplus);
                // backup files happens in backup_one_mod now too.
            }
        }
    }
    return $status;
}
    
    
function dataplus_backup_one_mod($bf,$preferences,$dataplus) {
    global $CFG;
	
	if (is_numeric($dataplus)) { // backwards compatibility
	    $dataplus = $DB->get_record('dataplus','id',$dataplus);
	}
	
	$instanceid = $dataplus->id;

    $status = true;
	
	
    fwrite ($bf,start_tag("MOD",3,true));

    //Print data data
	fwrite ($bf,full_tag("ID",4,false,$dataplus->id));
	fwrite ($bf,full_tag("MODTYPE",4,false,"dataplus"));
	fwrite ($bf,full_tag("COURSE",4,false,$dataplus->course));
	fwrite ($bf,full_tag("NAME",4,false,$dataplus->name));
	fwrite ($bf,full_tag("INTRO",4,false,$dataplus->intro));
	fwrite ($bf,full_tag("TIMEAVAILABLEFROM",4,false,$dataplus->timeavailablefrom));
	fwrite ($bf,full_tag("TIMEAVAILABLETO",4,false,$dataplus->timeavailableto));
	fwrite ($bf,full_tag("TIMEVIEWFROM",4,false,$dataplus->timeviewfrom));
	fwrite ($bf,full_tag("TIMEVIEWTO",4,false,$dataplus->timeviewto));
	fwrite ($bf,full_tag("REQUIREDENTRIES",4,false,$dataplus->requiredentries));
	fwrite ($bf,full_tag("REQUIREDENTRIESTOVIEW",4,false,$dataplus->requiredentriestoview));
	fwrite ($bf,full_tag("MAXENTRIES",4,false,$dataplus->maxentries));
	fwrite ($bf,full_tag("MAXENTRIESPERUSER",4,false,$dataplus->maxentriesperuser));
	fwrite ($bf,full_tag("STUDENTEDITING",4,false,$dataplus->studentediting));
	fwrite ($bf,full_tag("STUDENTEDITINGOTHER",4,false,$dataplus->studenteditingother));
	fwrite ($bf,full_tag("TEACHEREDITING",4,false,$dataplus->teacherediting));
	fwrite ($bf,full_tag("TEACHEREDITINGOTHER",4,false,$dataplus->teachereditingother));
    fwrite ($bf,full_tag("VIEWTABLABEL",4,false,$dataplus->viewtablabel));
    fwrite ($bf,full_tag("VIEWTABVISIBLE",4,false,$dataplus->viewtabvisible));
    fwrite ($bf,full_tag("SINGLERECORDTABLABEL",4,false,$dataplus->singlerecordtablabel));
    fwrite ($bf,full_tag("SINGLERECORDTABVISIBLE",4,false,$dataplus->singlerecordtabvisible));
    fwrite ($bf,full_tag("SEARCHTABLABEL",4,false,$dataplus->searchtablabel));
    fwrite ($bf,full_tag("SEARCHTABVISIBLE",4,false,$dataplus->searchtabvisible));
    fwrite ($bf,full_tag("ADDRECORDTABLABEL",4,false,$dataplus->addrecordtablabel));
    fwrite ($bf,full_tag("ADDRECORDTABVISIBLE",4,false,$dataplus->addrecordtabvisible));   
    fwrite ($bf,full_tag("EXPORTTABLABEL",4,false,$dataplus->exporttablabel));
    fwrite ($bf,full_tag("EXPORTTABVISIBLE",4,false,$dataplus->exporttabvisible));       
	fwrite ($bf,full_tag("SAVEBUTTONLABEL",4,false,$dataplus->savebuttonlabel));
    fwrite ($bf,full_tag("NAVIGATIONLIMIT",4,false,$dataplus->navigationlimit));	
	fwrite ($bf,full_tag("LISTPERPAGE",4,false,$dataplus->listperpage));
	fwrite ($bf,full_tag("RSSARTICLES",4,false,$dataplus->rssarticles));
	//fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$dataplus->timemodified));
		
	if (backup_userdata_selected($preferences,'dataplus',$dataplus->id)) {
        $dbonly = false;
    }
	else {
	   	$dbonly = true;
	}
	
    //Backup all the module files inc. SQLite3 db and supporting files and images
    $status = backup_dataplus_files_instance($bf,$preferences,$dataplus->id,$dbonly);
	    
    fwrite ($bf,end_tag("MOD",3,true));

    return $status;
}


function backup_dataplus_files_instance($bf,$preferences,$instanceid,$dbonly = false) {
	
    global $CFG;
    $status = true;

    //First we check to moddata exists and create it as necessary
	//in temp/backup/$backup_code  dir
	        
    $temp_dir = $CFG->dataroot."/temp/backup/".$preferences->backup_unique_code."/moddata/dataplus/";
    $data_dir = $CFG->dataroot."/".$preferences->backup_course."/".$CFG->moddata."/dataplus/".$instanceid;

    $status = check_and_create_moddata_dir($preferences->backup_unique_code);
    $status = check_dir_exists($temp_dir,true);

    if ($status) {
        if($dbonly){
            require_once($CFG->dirroot . '/mod/dataplus/sqlite3_db_dataplus.php');
            require_once($CFG->dirroot . '/mod/dataplus/dataplus_file_helper.php');

            $dataplus_file_helper = new dataplus_file_helper($instanceid);
            $dataplus_file_helper->copy_to_temp($instanceid . '.sqlite');
	    		
            $dataplus_db = new sqlite3_db_dataplus($instanceid);
            $dataplus_db->empty_user_data();
	    		
            $from = $dataplus_file_helper->get_temp_path().'/'.$instanceid.'.sqlite';
            $to   = $temp_dir.$instanceid.'/'.$instanceid.'.sqlite';
            check_dir_exists($temp_dir.$instanceid,true);
        }
        else {
            $from = $data_dir;
            $to   = $temp_dir.$instanceid;
        }
	    	
        if (file_exists($from)) {
            $status = backup_copy_file($from,$to);
        }
    }

    return $status;
}
    
	
function dataplus_check_backup_mods_instances($instance) {
    $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
    $info[$instance->id.'0'][1] = '';

    return $info;
}
	
	
function dataplus_check_backup_mods($course,$user_data=false,$backup_unique_code=null,$instances=null) {
    if (!empty($instances) && is_array($instances) && count($instances)) {
        $info = array();

        foreach ($instances as $id => $instance) {
            $info += dataplus_check_backup_mods_instances($instance);
	    }
	    
	    return $info;
    }
	
    // otherwise continue as normal
    //First the course data
    $info[0][0] = get_string("modulenameplural","dataplus");

    if ($ids = dataplus_ids($course)) {
        $info[0][1] = count($ids);
    } 
    else {
        $info[0][1] = 0;
    }
    
    return $info;

}


function dataplus_ids($course) {
    // stub function, return number of modules
    return 1;
}