<?php
/**
 *
 * @copyright &copy; 2010 The Open University
 * @author a.j.forth@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package dataplus
 */



//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Module functions                                                         //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function dataplus_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;

        default: return null;
    }
}

/**
 * add an instance to the dataplus table
 *
 * @param course $dataplus
 * @return int
 */
function dataplus_add_instance($dataplus){
    global $CFG, $DB;

    if (empty($data->assessed)) {
        $dataplus->assessed = 0;
    }

    $dataplus->timemodified = time();
    if (! $dataplus->id = $DB->insert_record('dataplus', $dataplus)) {
        return false;
    }

    return $dataplus->id;
}


/**
 * update the instance record in the dataplus table and initiate a check of the capabilities
 *
 * @param course $dataplus
 * @return mixed
 */
function dataplus_update_instance($dataplus){
    $dataplus->timemodified = time();
    $dataplus->id = $dataplus->instance;
        
    if(!update_record('dataplus', $dataplus)){
        return false;
    }

    dataplus_update_capabilities($dataplus);

    return true;
}


/**
 * Update capabilities related to editing data in the instance SQLite3 database
 *
 * @param course $dataplus
 * @param array $roles
 */
function dataplus_update_capabilities($dataplus, $roles = null){
    if (is_null($roles)) {
        if (class_exists('ouflags')) {
            $roles = array(
                'fullstudent'=>'student',
                'contributingstudent'=>'student',
                'teacher'=>'teacher');
        } else {
            $roles = array(
                'student'=>'student',
                'teacher'=>'teacher');
        }    
    }

    if (empty($dataplus->coursemodule)) {
        $cm = $DB->get_record('course_modules','module',$dataplus->module,'instance',$dataplus->id);
        $dataplus->coursemodule = $cm->id;
    }

    $context = get_context_instance(CONTEXT_MODULE,$dataplus->coursemodule);

    foreach ($roles as $role=>$type) {
        $id = $DB->get_field('role', 'id', 'shortname', $role);

        if (empty($id)) {
            continue;
        }

        $roleediting = $type.'editing';

        if ($dataplus->$roleediting == '0') {
            assign_capability('mod/dataplus:dataeditown', CAP_PROHIBIT, $id, $context->id, true);
        } else if ($dataplus->$roleediting == '1'){
            assign_capability('mod/dataplus:dataeditown', CAP_ALLOW, $id, $context->id, true);
        }

        $roleeditingothers = $type . 'editingother';

        if (!isset($dataplus->$roleeditingothers) || $dataplus->$roleeditingothers == '0') {
            assign_capability('mod/dataplus:dataeditothers', CAP_PROHIBIT, $id, $context->id, true);
        } else if ($dataplus->$roleeditingothers == '1') {
            assign_capability('mod/dataplus:dataeditothers', CAP_ALLOW, $id, $context->id, true);
        }
    }
}


/**
 * delete a record from the dataplus table
 *
 * @param int $id
 * @return boolean
 */
function dataplus_delete_instance($id) {
    global $CFG, $COURSE, $context;

    require_once($CFG->dirroot . '/lib/filelib.php');

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'dataplus', 'db'.$mod_inst_id, $submission->id);

    foreach ($files as $f) {
        if (!$f->delete()) {
            return false;
        }
    }

    return true;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * @param int $course Course id
 * @param int $user User id
 * @param int $mod  
 * @param int $scorm The scorm id
 * @return object
 */
function dataplus_user_outline($course, $user, $mod, $dataplus){
    global $CFG;

    $sql = '';
    $actions = dataplus_get_view_actions();
    $result = new stdClass;

    foreach ($actions as $action) {
        $sql .= " (SELECT COUNT(\"action\")
                   FROM \"{log}\"
                   WHERE \"action\" = {$action}
                   AND \"course\" = {$course->id}
                   AND \"userid\" = {$user->id}
                   AND \"cmid\" = {$mod->id}) as {$action}";
    }
            
    $sql = "SELECT" . $sql . " FROM \"{dataplus_actions}\"";

    if ($actions_result = $DB->get_record_sql($sql)) {
        $summary = '';

        foreach ($actions as $action) {
            if ($actions_result->$action > 0) {
                $summary .= get_string('useroutline_'.$action, 'dataplus').$actions_result->$action;
            }
        }

        $result->info = $summary;

        $sql = "SELECT time
                FROM \"{log}\"
                WHERE \"course\" = {$course->id}
                AND \"userid\" = {$user->id}
                AND \"cmid\" = {$mod->id}) as {$action}";

        $time_result = $DB->get_record_sql($sql,true);
        $result->time = $time_result->time;
    } else {
        $result->info = get_string('useroutline_noactivity', 'dataplus');
    }

    return $result;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param int $course
 * @param int $user
 * @param int $mod
 * @param int $scorm
 * @return boolean
 */
function dataplus_user_complete($course, $user, $mod, $dataplus){
    $sql = " (SELECT \"info\", \"time\"
              FROM \"{log}\"
              WHERE \"course\" = {$course->id}
              AND \"userid\" = {$user->id}
              AND \"cmid\" = {$mod->id}) as {$action}";

    if ($logs = $DB->get_records_sql($sql)) {
        print_simple_box_start();

        echo $user->id;

        foreach ($logs as $log ){
            echo date('d/m/Y H:i:s', $log->time).' '.$log->description.' '.'<br/>';
        }

        print_simple_box_end();
    } else {
        print_string('useroutline_noactivity', 'dataplus');
    }
}


/**
* Returns an array of possible actions
* 
* @return array
*/
function dataplus_get_view_actions(){
    return array(
        'view',
        'search',
        'insert',
        'update',
        'delete',
        'createcolumn',
        'editcolumn',
        'deletecolumn',
        'templatesaved');
}


/**
 * Serves associated files
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return mixed
 */
function dataplus_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload){
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $postid = (int)array_shift($args);

    if (!$dataplus= $DB->get_record('dataplus', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_dataplus/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    /**
     * TODO - Security for group acces for a supporting files.
     */

    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    exit;
}



//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Page functions                                                           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////  

/**
 * Returns the number of entries in the database and adjust it for pending operations.
 * Used for completion and view limits.  Normally the SQLite3 object count_user_entries() should
 * be used.
 * 
 * @return int
 */
function dataplus_get_user_entries_count(){
    global $dataplus_db, $mode;

    $entries = $dataplus_db->count_user_entries();

    //increase the number if an insert is pending
    if (!empty($_POST) && ($mode == 'insert' || $mode == 'insertbelowlimit')) {
        $entries++;
    }

    //decrease the number if a delete is pending
    if (!empty($_POST) && ($mode == 'delete')) {
        $entries--;
    }

    return $entries;
}


/**
 * Sets up globals used in DataPlus, checks course login and basic $PAGE settings
 *
 */
function dataplus_base_setup(){
    require_once('dataplus_file_helper.php');
    require_once('sqlite3_db_dataplus.php');

    global $PAGE, $DB, $id, $mode, $cm, $CFG, $COURSE, $dataplus, $dataplus_filehelper, $dataplus_db, $context, $currentgroup, $groupmode, $editing_modes;

    $id = required_param('id', PARAM_INT);
    $mode = optional_param('mode', null, PARAM_TEXT);

    if (! $cm = get_coursemodule_from_id('dataplus', $id)) {
        print_error("Course Module ID was incorrect");
    }

    if (! $COURSE = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error("Course is misconfigured");
    }

    if (! $dataplus = $DB->get_record("dataplus", array("id"=>$cm->instance))) {
        print_error("Course module is incorrect");
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    require_course_login($COURSE->id, true, $cm);

    //instantiate the file helper and make sure temp folder is clear
    $dataplus_filehelper = new dataplus_file_helper($dataplus->id);

    //check whether we need to lock the database by seeing if any data has been submitted and it's not a search
    if(empty($_POST) || strstr($mode,'search')){
        $lock = false;
    } else {
        $lock = true;
    }

    //instantiate the SQLite3 database helper.
    $dataplus_db = new sqlite3_db_dataplus($dataplus->id,$lock);

    groups_print_activity_menu($cm, $CFG->wwwroot, true);

    $currentgroup = groups_get_activity_group($cm);

    if (empty($currentgroup)) {
        $currentgroup = 0;
    }

    $groupmode = groups_get_activity_groupmode($cm);
    $editing_modes = dataplus_get_edit_modes();

    $PAGE->set_context($context);
    $PAGE->set_cm($cm);
}


/**
 * Closes the objects instantiated in setup
 *
 */
function dataplus_base_close(){
    global $dataplus_db, $dataplus_filehelper;

    $dataplus_db->close();

    if (!empty($dataplus_filehelper)) {
       $dataplus_filehelper->close();
    }
}


/**
 * Sets up the main globals used in dataplus and prints the header.  
 * Also controls screens when a database needs setup actions
 */
function dataplus_page_setup($filepath, $querystring, $page_title, $js=null, $js_init=null, $css=null){
    global $PAGE, $id, $mode, $cm, $COURSE, $dataplus, $context, $dataplus_db, $CFG, $OUTPUT;

    $PAGE->set_url($filepath,$querystring);
    $PAGE->set_title($page_title);
    $PAGE->set_heading($COURSE->fullname);
    $PAGE->requires->js('/mod/dataplus/dataplus.js');

    if (!is_null($js)) {
        $PAGE->requires->js($js);
    }

    if (!is_null($js_init)) {
        $functions = explode('*-*',$js_init);

        foreach ($functions as $function) {
            if (empty($function)) {
                continue;
            }

            $name_var = explode("*^*",$function);
            $params = explode("*$*",$name_var[1]);

            $PAGE->requires->js_init_call($name_var[0],$params);
        }
    }

    if (!is_null($css)) {
        $PAGE->requires->css($css);
    }

    $fid = optional_param('fid', null, PARAM_TEXT);
    $navigation = build_navigation('', $cm);

    echo $OUTPUT->header();
 
    //if the database isn't used, and we're not in setup mode, show the options to set up tables or import an 
    //existing dataplus database.
    if ($dataplus_db->unused_database() && $mode != 'dbsetup' && $mode != 'dbmanage') {
        echo '<p>'.get_string('databasenotsetup','dataplus').'</p>';

        if (isloggedin() && has_capability('mod/dataplus:databaseedit', $context)) {
            $url = $CFG->wwwroot.'/mod/dataplus/manage.php?id='.$cm->id.'&amp;mode=dbsetup';

            echo '<p><a href="'.$url.'">'.get_string('databasesetup','dataplus').'</a></p>';

            $url = $CFG->wwwroot.'/mod/dataplus/import.php?id='.$cm->id.'&amp;mode=dbsetup';

            echo '<p><a href="'.$url.'">'.get_string('importdb','dataplus').'</a></p>';
        }

        echo $OUTPUT->footer();
        dataplus_base_close();
        exit;
    }

    //check whether a requiredentries reminder needs to be printed
    if ($dataplus->requiredentries > 0 && has_capability('mod/dataplus:dataeditown', $context)) {
        $entries = dataplus_get_user_entries_count();

        if ($entries < $dataplus->requiredentries) {
            $additions->total = $dataplus->requiredentries;
            $additions->created = $entries;

            echo '<p>'.get_string('requiredentriesreminder','dataplus', $additions).'</p>';
        }
    }

    //check whther a requiredentriestoview reminder needs to be printed.
    if ($dataplus->requiredentriestoview > 0 && has_capability('mod/dataplus:dataeditown', $context)) {
        if (!isset($entries)) {
            $entries = dataplus_get_user_entries_count();
        }

        if ($entries < $dataplus->requiredentriestoview) {
            $additions->total = $dataplus->requiredentriestoview;
            $additions->created = $entries;

            echo '<p>'.get_string('requiredentriestoviewreminder','dataplus', $additions).'</p>';
            $url = $CFG->wwwroot.'/mod/dataplus/view.php?id=' . $cm->id . '&amp;mode=insertbelowlimit';

            if ($mode != 'insertbelowlimit') {
                echo '<p><a href="'.$url.'">'.get_string('addrecord','dataplus').'</a></p>';
                echo $OUTPUT->footer();
                dataplus_base_close();
                exit;
            }
        } else if ($mode == 'insertbelowlimit'){
            $mode = 'insert';
            echo '<p>'.get_string('requiredentriestoviewdone','dataplus',$dataplus->requiredentriestoview).'</p>';
        }
    }

    $script_path = substr($_SERVER["SCRIPT_NAME"],strpos($_SERVER["SCRIPT_NAME"],'mod/dataplus'));
    $url = $CFG->wwwroot.'/'.$script_path.'?&amp;mode='.$mode.'&amp;id='.$id;

    if (!empty($fid)) {
        $url .= "&amp;fid=".$fid;
    }

    //print the groups menu
    $groups = groups_print_activity_menu($cm, $url, true);
    
    if (strlen($groups) > 0) {
        echo $groups;
        echo "<br/>";
    }

    $OUTPUT->heading(format_string($dataplus->name));
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Group functions                                                          //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

/**
 * Check the rights of a group to an item.  By default, checks are for read only view.
 * An error message can be printed if specified
 *
 * @param obj $item
 * @param boolean $editing - if set to false function checks 'read only' rights for a record
 * @param boolean $print_error
 * @return boolean
 */
function dataplus_check_groups($item, $editing = false, $print_error = false){
    global $currentgroup, $groupmode, $id, $CFG, $context;

    $item_group_id = (int) $item->group_id;

    if ((!$editing && ($groupmode == 0 ||
                      $currentgroup == 0 ||
                      $item_group_id == 0 ||
                      $item_group_id == '' ||
                      $groupmode == 2 ||
                      $currentgroup == $item_group_id)) ||
        ($editing && ($groupmode == 0 ||
                      $item_group_id == 0 ||
                      $item_group_id == '' ||
                      groups_is_member($currentgroup) ||
                      has_capability('mod/dataplus:databaseedit', $context)))) {
        return true;
    }

    if($print_error){
        print_error(get_string('groupmember','dataplus'), $CFG->wwwroot.'/mod/dataplus/view.php?mode=view&amp;id=' . $id);
    }

    return false;
}


/**
 * Check a user can edit a record from the creator_id of the record
 *
 * @param int $creator_id
 * @return boolean
 */
function dataplus_check_capabilities($creator_id){
    global $USER, $context;

    if (has_capability('mod/dataplus:databaseedit', $context) ||
        (has_capability('mod/dataplus:dataeditown', $context) && $USER->id == $creator_id) ||
        has_capability('mod/dataplus:dataeditothers', $context)){
        return true;
    }

    return false;
}


/**
 * Returns the parameters required when making a records query that will only include results 
 * from a particular group
 *
 * @param array $parameters
 * @return array
 */
function dataplus_get_restricted_groups_parameters($parameters = array()){
    global $groupmode, $currentgroup;

    if($groupmode == 0){
        return $parameters;
    }

    $i = sizeof($parameters);

    $parameters[$i]->sub = array();
    $parameters[$i]->sub[0]->name = 'group_id';
    $parameters[$i]->sub[0]->value = $currentgroup;
    $parameters[$i]->sub[0]->operator = 'equals';

    $parameters[$i]->sub[1]->name = 'group_id';
    $parameters[$i]->sub[1]->value = '0';
    $parameters[$i]->sub[1]->operator = 'equals';
    $parameters[$i]->sub[1]->andor = 'OR';

    $parameters[$i]->sub[2]->name = 'group_id';
    $parameters[$i]->sub[2]->value = '';
    $parameters[$i]->sub[2]->operator = 'equals';
    $parameters[$i]->sub[2]->andor = 'OR';

    return $parameters;
}


/**
 * Returns the name of a group from it's id (including 'all participants' if group is 0
 *
 * @param int $id
 * @return string
 */
function dataplus_get_group_name($id){
    if($id == 0){
        $name = get_string('allparticipants');
    } else {
        $group = groups_get_group($id);
        $name = $group->name;
    }

    return $name;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Log functions                                                            //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////  

/**
 * Adds an action in the module to the logs table
 *
 * @param string $action
 */
function dataplus_log($action){
    global $COURSE, $USER, $cm;

    if (is_null($cm)) {
        return;
    }

    add_to_log($COURSE->id, 'dataplus',$action, $_SERVER['REQUEST_URI'],'',$cm->id,$USER->id);
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Template helper functions                                                //
//                                                                          //
////////////////////////////////////////////////////////////////////////////// 

/**
 * Get a default presentation template.  Includes all the relevant fields with labels plus edit and delete.
 * Includes the labels as identifiers unless $use_names is true.
 *
 * @param boolean $use_names
 * @return string
 */
function dataplus_get_default_view_template($use_names = false){
    global $dataplus_db;

    $parameters = dataplus_get_restricted_groups_parameters();
    $fields = $dataplus_db->list_dataplus_table_columns(false,$parameters);

    if (sizeof($fields)==0) {
        return '';
    }

    $html = '<table class="record_template">';

    foreach ($fields as $field) {
        if ($use_names) {
            $hook = $field->name;
        } else {
            $hook = $field->label;
        }

        $html .= "<tr>";
        $html .= "<td><strong>{$field->label}</strong></td>";
        $html .= "<td>";
        $html .= "[[{$hook}]]";
        $html .= "</td></tr>";
    }

    $html .= "<tr>";
    $html .= '<td colspan="2">';

    foreach (dataplus_detail_supporting_actions() as $action) {
        $html .= '**'.$action->label.'**';
    }

    $html .= '</td>';
    $html .= "</tr>";
    $html .= "</table>";

    return $html;
}


/**
 * Get a default header for a template.
 * 
 * @return string
 */
function dataplus_get_default_header(){
    return <<<EOF
<div class="dataplus_record_navigation">##record_navigation##</div>
<div class="dataplus_record_count">##record_count##</div>
EOF;
}


/**
 * Get a default footer for a template.
 * 
 * @return string
 */
function dataplus_get_default_footer(){
    global $dataplus, $mode;

    $html = '';

    if (dataplus_allow_comments()) {
        $html .= '<div class="dataplus_add_comment">##add_comment##</div>';
    }

    $html .= <<<EOF
<div class="dataplus_record_count">##record_count##</div>
<div class="dataplus_record_navigation">##record_navigation##</div>
EOF;

    return $html;
}


/**
 * Get default CSS for a template.
 * 
 * @return string
 */
function dataplus_get_default_CSS(){
    global $CFG;

    return file_get_contents($CFG->wwwroot . '/mod/dataplus/template_css_view.css');
}


/**
 * Get default CSS for an addrecord template.
 * 
 * @return string
 */
function dataplus_get_default_addrecord_CSS(){
    global $CFG;

    return file_get_contents($CFG->wwwroot . '/mod/dataplus/template_css_addrecord.css');
}


/**
 * Get the default comments template.
 * 
 * @return string
 */
function dataplus_get_default_comments(){
    return <<<EOF
<div class="dataplus_comment">
    <table class="dataplus_comment_output">
        <tr>
            <td class="dataplus_creator_details">##creator## ##created_time##</td>
        </tr>
        <tr>
           <td class="dataplus_comment_cell">[[comment]]</td>
        </tr>
        <tr>
            <td class="dataplus_comment_actions">**edit** **delete**</td>
        </tr>
    </table>
</div>
EOF;
}


/**
 * Get a default add record template.  Includes all the relevant fields.
 * Includes the labels as identifiers unless $use_names is true.
 *
 * @param boolean $use_names
 * @return string
 */
function dataplus_get_default_addrecord_template($use_names = false, $mode = 'addrecord'){
    global $dataplus_db;

    $parameters = dataplus_get_restricted_groups_parameters();
    $fields = $dataplus_db->list_dataplus_table_columns(false,$parameters);

    if (sizeof($fields)==0) {
        return '';
    }

    $html = '';

    foreach ($fields as $field) {
        if ($use_names) {
            $hook = $field->name;
        } else {
            $hook = $field->label;
        }

        $html .= "[[{$hook}]]";
    }

    $html .= '**addcancel**';

    return $html;
}


/**
 * get details of supporting functions for template headers
 * 
 * @return array
 */
function dataplus_detail_supporting_functions(){

    $functions[0]->name = 'record_count';
    $functions[0]->label = get_string('record_count','dataplus');
            
    $functions[1]->name = 'record_navigation';
    $functions[1]->label = get_string('record_navigation','dataplus');

    return $functions;
}


/**
 * get details of supporting actions for record templates
 * 
 * @return array
 */
function dataplus_detail_supporting_actions(){
    $actions[0]->name = 'edit';
    $actions[0]->label = get_string('edit','dataplus');
            
    $actions[1]->name = 'delete';
    $actions[1]->label = get_string('delete','dataplus');

    return $actions;
}


/**
 * get details of supporting record information
 * 
 * @return array
 */
function dataplus_detail_supporting_record_information(){
    $info[0]->name = 'record_no';
    $info[0]->label = get_string('record_no','dataplus');

    return $info;
}


/**
 * Returns HTML for template menu
 * 
 * @return string
 */
function dataplus_get_template_menu_html(){
    if (class_exists('ouflags')) {
        $scnd_class = 'dataplus_templatemenu_OU';
    } else {
        $scnd_class = 'dataplus_templatemenu_nonOU';
    }

    return <<<EOF
<div class="dataplus_templatemenu {$scnd_class}">
    <div class="dataplus_templatemenu_topright">
        <div class="dataplus_templatemenu_bottomright">
            <div class="dataplus_templatemenu_bottomleft">
                <ul>
                    [[template content]]
                </ul>
            </div>
        </div>
    </div>
</div>
EOF;
}


/**
 * Prints the record menu used on the template screen
 * 
 * @param string $mode
 * @return string
 */
function dataplus_get_template_record_menu($mode = null){
    global $dataplus_db;

    $parameters = dataplus_get_restricted_groups_parameters();
    $columns = $dataplus_db->list_dataplus_table_columns(false,$parameters);

    $str_add_fields = get_string('addfieldstorecord', 'dataplus');
    $str_add_actions = get_string('addactionstorecord', 'dataplus');
    $str_add_additional = get_string('addadditionaltorecord', 'dataplus');
    $str_add_info = get_string('addinfo', 'dataplus'); 

    $html = "<li><strong>{$str_add_fields}</strong></li>";

    foreach ($columns as $column ){
        $html .= "<li><a onclick=\"dataplusUpdateTextbox('[[{$column->label}]]','id_record'); return false;\" href=\"#\">[[{$column->label}]]</a></li>";
    }

    $html .= "<li><br/><strong>{$str_add_actions}</strong></li>";

    $actions = dataplus_detail_supporting_actions();
    
    if ($mode == 'view') {
        $actions_size = sizeof($actions);

        $actions[$actions_size]->name  = 'more';
        $actions[$actions_size]->label = get_string('more','dataplus');
    }

    if ($mode == 'addrecord') {
        $html .= "<li><a onclick=\"dataplusUpdateTextbox('**addcancel**','id_record'); return false;\" href=\"#\">**addcancel**</a></li>";
        return str_replace('[[template content]]',$html,dataplus_get_template_menu_html());
    }

    foreach ($actions as $action) {
        $html .= "<li><a onclick=\"dataplusUpdateTextbox('**{$action->label}**','id_record'); return false;\" href=\"#\">**{$action->label}**</a></li>";
    }

    $html .= "<li><br/><strong>{$str_add_additional}</strong></li>";

    $supporting_columns = $dataplus_db->detail_content_table_supporting_columns();

    foreach ($supporting_columns as $column) {
        if($column->label == ''){
            continue;
        }

        $html .= "<li><a onclick=\"dataplusUpdateTextbox('##{$column->label}##','id_record'); return false;\" href=\"#\">##{$column->label}##</a></li>";
    }

    $html .= "<li><br/><strong>{$str_add_info}</strong></li>";
    
    foreach (dataplus_detail_supporting_record_information() as $info) {
        $html .= "<li><a onclick=\"dataplusUpdateTextbox('++{$info->label}++','id_record'); return false;\" href=\"#\">++{$info->label}++</a></li>";
    }

    return str_replace('[[template content]]',$html,dataplus_get_template_menu_html());
}


/**
 * Prints the comment menu used on the template screen
 * 
 * @return string
 */
function dataplus_get_template_comments_menu(){
    global $dataplus_db;

    $str_comment = get_string('comment', 'dataplus');
    $str_add_comment = get_string('addcomment', 'dataplus');
    $str_add_actions = get_string('addactionstorecord', 'dataplus');
    $str_add_additional = get_string('addadditionaltorecord', 'dataplus');

    $html = "<li><strong>{$str_add_comment}</strong></li>";
    $html .= "<li><a onclick=\"dataplusUpdateTextbox('[[comment]]','id_comments'); return false;\" href=\"#\">[[{$str_comment}]]</a></li>";
    $html .= "<li><br/><strong>{$str_add_actions}</strong></li>";

    foreach (dataplus_detail_supporting_actions() as $action) {
        $html .= "<li><a onclick=\"dataplusUpdateTextbox('**{$action->label}**','id_comments'); return false;\" href=\"#\">**{$action->label}**</a></li>";
    }

    $html .= "<li><br/><strong>{$str_add_additional}</strong></li>";

    $supporting_columns = $dataplus_db->define_editor_columns();

    foreach ($supporting_columns as $column) {
        if ($column->label == '') {
            continue;
        }

        $html .= "<li><a onclick=\"dataplusUpdateTextbox('##{$column->label}##','id_comments'); return false;\" href=\"#\">##{$column->label}##</a></li>";
    }

    return str_replace('[[template content]]',$html,dataplus_get_template_menu_html());
}


/**
 * Prints the header/footer menu used on the template screen
 * 
 * @return string
 */
function dataplus_get_template_headerfooter_menu($position){
    $str_add_record_functions = get_string('addrecordfunctions', 'dataplus');

    $html = "<li><strong>{$str_add_record_functions}</strong></li>";

    foreach (dataplus_detail_supporting_functions() as $function) {
        if ($function->label == '') {
            continue;
        }

        $html .= "<li><a onclick=\"dataplusUpdateTextbox('##{$function->label}##','id_{$position}'); return false;\" href=\"#\">##{$function->label}##</a></li>";
    }

    return str_replace('[[template content]]',$html,dataplus_get_template_menu_html());
}


/**
 * Removes any escapes, etc, from values to be displayed
 *
 * @param string $value
 * @return string
 */
function dataplus_prepare_value($value){
    //turn replace the muliple value divider with line breaks
    $value = str_replace('<<MM>>','<br/>',$value);
    $value = str_replace("\'","'",$value);
    return $value;
}


/**
 * Takes a results of a database query and a template and prints the output for each record.
 * $clear_actions can be used to remove edit and delete links if required.
 *
 * @param string $template
 * @param array/obj $results
 * @param boolean $clear_actions
 */
function dataplus_print_template_output($template, $results, $clear_actions = false){
    global $CFG, $dataplus_db, $dataplus_filehelper, $context;

    $smarty = dataplus_new_smarty();
    $parameters = dataplus_get_restricted_groups_parameters();  
    $cols = $dataplus_db->list_dataplus_table_columns(true,$parameters);

    if (sizeof($cols) == 0) {
        return;
    }

    $records = '';

    if (!is_array($results)) {
        $results = array($results);
    }

    $rec = 1;

    foreach ($results as $result) {
        $record_template = $template;

        if (!$clear_actions && dataplus_check_groups($result, true) &&
            dataplus_check_capabilities($result->creator_id)){
            $final_clear_actions = false;
        } else {
            $final_clear_actions = true;
        }

        foreach (dataplus_detail_supporting_actions() as $action) {
            $record_template = dataplus_create_record_icon($action->name,$action->label,$record_template,$result->id,
                                                           $result->creator_id,$result->group_id,$final_clear_actions);
        }

        $record_template = dataplus_create_more_link($result->id, $record_template);

        foreach (dataplus_detail_supporting_record_information() as $info) {
            $record_template = dataplus_create_supporting_information($info->name,$rec,$record_template);
        }

        foreach ($result as $name=>$value) {
            $form_field_type = null;
            $type = null;
            $value = dataplus_prepare_value($value);

            foreach ($cols as $col) {
                if ($col->name == $name) {
                    $form_field_type = $col->form_field_type;
                    $type = $col->type;
                    break;
                }
            }

            //as well as checking $form_field_type is set, this has the effect of 
            //stoping fields being printed if not in the correct group. 
            if (empty($type)) {
                $record_template = str_replace("[[{$name}]]",'',$record_template);
                $record_template = str_replace("##{$name}##",'',$record_template);
                continue;
            }

            if ($form_field_type == 'date' && !empty($value)) {
                $value = date('d F Y',$value);
            } else if (($form_field_type == 'datetime' || (empty($form_field_type) && $type == 'date')) && !empty($value)) {
                $value = date('d F Y H:i',$value);
            } else if($form_field_type == 'image' && !empty($value)) {
                $path = $dataplus_filehelper->get_image_file_path($value);

                $alt_name = $name."alt".$dataplus_db->get_supporting_suffix();

                foreach ($result as $n=>$v) {
                    if ($n == $alt_name) {
                        $alt = $v;
                        break;
                    }
                }

                $value = "<img src=\"{$path}\" alt=\"{$alt}\" title=\"{$alt}\"/>";
            } else if ($form_field_type == 'file' && !empty($value)){
                $path = $dataplus_filehelper->get_file_file_path($value);
                $value = "<a href=\"{$path}\">".$dataplus_filehelper->get_file_name($context->id,'file',$value)."</a>";
            } else if ($form_field_type == 'url' && !empty($value)){
                $label_name = $name.'desc'.$dataplus_db->get_supporting_suffix();

                foreach ($result as $n=>$v) {
                    if ($n == $label_name) {
                        $label = $v;
                        break;
                    }
                }

                if (empty($label)) {
                    $label = $value;
                }

                if (strtolower(substr($value,0,7)) != 'http://') {
                    $value = 'http://' . $value;
                }

                $value = "<a onclick = \"this.target='link{$rec}'; return openpopup('{$value}','link{$rec}');\" href=\"{$value}\">{$label}</a>";
            } else if($form_field_type == 'boolean'){
                if ($value == 1) {
                    $value = get_string('true', 'dataplus');
                } else if($value == 0){
                    $value = get_string('false', 'dataplus');
                }
            }

            $smarty->assign($name,$value);
            $record_template = str_replace("[[{$name}]]",$value,$record_template);
            $record_template = str_replace("##{$name}##",$value,$record_template);
        }

        $records .= "<a name=\"{$result->id}\"></a><div class=\"dataplus_record\">{$record_template}</div>";

        $rec++;
    }

    $smarty->display('string:' . $records);
}


/**
 * Takes a template header or footer, adds divs, record counts and navigation if required and displays it.
 *
 * @param string $input
 * @param array $parameters
 * @param array $col_parameters
 */
function dataplus_print_template_headerfooter_output($position, $input, $parameters, $col_parameters = null){
    global $mode;

    $smarty = dataplus_new_smarty();

    $input = str_replace('##record_count##',dataplus_print_record_count($parameters),$input);
    $input = str_replace('##record_navigation##',dataplus_print_record_navigation($parameters,$col_parameters),$input);

    $changecomment = optional_param('changecomment',0,PARAM_INT);

    if ($mode == 'single') {
        if ($changecomment == 0 || $changecomment == 2) {
            $input = str_replace('##add_comment##',dataplus_print_add_comment(),$input);
        } else {
            $input = str_replace('##add_comment##','',$input);
        }
    }

    $input = "<div id=\"dataplus_template_{$position}\">{$input}</div>";
    $smarty->display('string:' . $input);
}


/**
 * Print comment template output
 * 
 * @param string template for comments
 * @param int $rid
 * @param int $ignore_end
 * @param int $ignore_start
 * @param int $single
 * @param boolean $return_html
 */
function dataplus_print_template_comments_output($input, $rid = NULL, $ignore_end = NULL, $ignore_start = NULL, $single = NULL, $return_html = false){
    global $dataplus_db;

    $output = '';

    if (is_null($single)) {
        $raw = $dataplus_db->get_record_comments($rid, $ignore_end, $ignore_start, dataplus_get_restricted_groups_parameters());
    } else {
        $raw = $dataplus_db->get_comment($single, dataplus_get_restricted_groups_parameters());
    }

    $output .="<a name=\"comments\"></a>";

    if(empty($raw)){
        return $output;
    }
    
    if (is_null($single)) {
        foreach ($raw as $comment) {
            $output .= dataplus_get_comment_html($input, $comment);
        }
    } else {
        $output = dataplus_get_comment_html($input, $raw);
    }

    if (!$return_html) {
        $smarty = dataplus_new_smarty();
        $smarty->display('string:'.$output);
    } else {
        return $output;
    }
}


/**
 * Return comment HTML
 * 
 * @param string $input
 * @param array obj $comment
 * @return string
 */
function dataplus_get_comment_html($input, $comment){
    global $dataplus_db, $context, $USER;

    $editor_cols = $dataplus_db->define_editor_columns();
    $comment_html = $input;

    foreach ($editor_cols as $ec) {
        $ec_name = $ec->name;

        if ($ec->type == 'date') {
            $value = date('d F Y H:i:s',$comment->$ec_name);
        } else {
            $value = $comment->$ec_name;
        }

        $rep = $ec->label.": ".$value;
        $comment_html = str_replace('##'.$ec_name.'##',$rep,$comment_html);
     }

     $comment_html = str_replace('[[comment]]',$comment->comment,$comment_html);
     
     if (has_capability('mod/dataplus:databaseedit', $context) || $USER->id == $comment->creator_id) {
        $comment_html = str_replace("**edit**", dataplus_print_edit_comment($comment->id), $comment_html);
        $comment_html = str_replace("**delete**", dataplus_print_delete_comment($comment->id), $comment_html);
     } else {
        $comment_html = str_replace("**edit**", "", $comment_html);
        $comment_html = str_replace("**delete**", "", $comment_html);
     }

     return $comment_html;
}



/**
 * Print paging links for a set of query results (prints nothing if the results fit on one page).
 *
 * @param array $parameters
 * @param array $col_parameters
 * @return mixed
 */
function dataplus_print_record_navigation($parameters = null, $col_parameters = array()){
    global $dataplus_db, $dataplus, $CFG, $cm, $mode;

    $output = '';

    $cols = $dataplus_db->list_dataplus_table_columns(false,$col_parameters);

    //if there are no user specified cols, do nothing
    if (sizeof($cols) == 0) {
        return;
    }

    //count the number of records returned by the query parameters
    $count = $dataplus_db->count_dataplus_database_query($parameters);

    if ($mode == 'single') {
        $page_limit = 1;
    } else {
        $page_limit = (int) $dataplus->listperpage;
    }

    //find out the number of the first record on the page
    $page_start = dataplus_get_page_start();

    //find out the number of pages
    $pages = intval($count/$page_limit);

    //if the number of pages is higher, up the number of pages by 1
    if ($count % $page_limit) {
        $pages++;
    }

    //check whether there is somewhere to go back to...
    if ($page_start != 0) {
        $back_page = $page_start - $page_limit;
    } else {
        $back_page = null;
    }

    //check whether there is somewhere to go forwards to...
    if (((($page_start+$page_limit) / $page_limit) < $pages) && $pages != 1) {
        $next_page = $page_start + $page_limit;
    } else {
        $next_page = null;
    }

    //if everything fits on one page, do nothing
    if (is_null($back_page)  && is_null($next_page)) {
        return;
    }

    //otherwise start printing paging
    $lang_next = get_string('next', 'dataplus');
    $lang_previous = get_string('previous', 'dataplus');

    $url = $CFG->wwwroot.'/mod/dataplus/view.php?id='.$cm->id.'&amp;mode=' . $mode . '&amp;ps=';
    
    //find out what the total number (limit) of page links that can be displayed in the navigation

    if(empty($dataplus->navigationlimit)){
       $navigation_limit = 15;
    } else {
       $navigation_limit = $dataplus->navigationlimit;
    }

    // if the limit on links is greater than the number of pages of results, the first page is 0 and the 
    // last is the total number of pages...
    if($pages <= $navigation_limit){
        $start = 0;
        $stop  = $pages;
    } else {  
        //otherwise the number of the current page should be central on the navigation list, so work out what 
        //(limit -1)/2 is ($navigation_limit is always an odd number)...
        $page = $page_start / $page_limit;
        $diff = (($navigation_limit-1)/2);

        //then set the start to be the current page minus the diff and the stop to be the current page + diff...
        $start = $page - $diff;
        $stop  = ($page + $diff)+1;

        // if the $page - $start is less than zero or $stop is greater than the number of pages
        //, then the page number cannot be central 
        // and display the number of pages set in the limit 
        // so abandon the attempt and set the start to 0 and the stop to the limit.

        if ($start<0) {
            $start = 0;
            $stop = $navigation_limit;
        }

        if ($stop>$pages) {
            $start = $start - ($stop - $pages);
            $stop = $pages;
        }

        //and finally display a link to return to the first record.
        $lang_first = get_string('first', 'dataplus');

        if($page_start>0) {
           $output .= "<span class=\"dataplus_nav_first\"><a href=\"{$url}0\">{$lang_first}</a> </span>";
        } else {
           $output .= "<span class=\"dataplus_nav_first\">" . $lang_first . " </span>";
        }
    }

    if (!is_null($back_page)) {
        $output .= "<span class=\"dataplus_nav_previous\"><a href=\"{$url}{$back_page}\">{$lang_previous}</a> </span>";
    } else {
        $output .= "<span class=\"dataplus_nav_previous\">" . $lang_previous . " </span>";
    }

    $output .= "&nbsp;";

    for ($i=$start; $i<$stop; $i++) {
        $start = $i * $page_limit;
        $pageno = $i+1;

        if ($page_start == $start) {
            $output .= "<span class=\"dataplus_nav_page\">" . $pageno . " </span>";
        } else {
            $output .= "<span class=\"dataplus_nav_page\"><a href=\"{$url}{$start}\">{$pageno}</a> </span>";
        }
    }

    if(!is_null($next_page)){
        $output .= "<span class=\"dataplus_nav_next\"><a href=\"{$url}{$next_page}\">{$lang_next}</a> </span>";
    } else {
        $output .= "<span class=\"dataplus_nav_next\">" . $lang_next . " </span>";
    }

    //add a link to the last page
    if($pages > $navigation_limit){
        $last_page_first_record = ($pages * $page_limit)-1;

        $lang_last = get_string('last', 'dataplus');
       
        if($page_start<$last_page_first_record){
           $output .= "<span class=\"dataplus_nav_last\"><a href=\"{$url}{$last_page_first_record}\">{$lang_last}</a></span>";
        } else {
           $output .= "<span class=\"dataplus_nav_last\">" . $lang_last . "</span>";
        }
    }

    return $output;
}


/**
 * print the start and end numbers of records printed on a screen and the record total
 *
 * @param array $parameters
 */
function dataplus_print_record_count($parameters = null){
    global $dataplus_db, $dataplus, $mode;

    $page_start = dataplus_get_page_start() + 1;
    $limit = (int) $dataplus->listperpage;
    $page_end = $page_start + ($limit-1);
    $count = $dataplus_db->count_dataplus_database_query($parameters);

    if ($page_end>$count) {
        $page_end = $count;
    }

    if ($count == $page_start || $mode == 'single') {
        $additions->start = $page_start;
        $additions->end = $count;

        $lang_record_count = get_string('recordcountsingle', 'dataplus',$additions);
    } else {
        $additions->start = $page_start;
        $additions->end = $page_end;
        $additions->total = $count;

        $lang_record_count = get_string('recordcount', 'dataplus',$additions);
    }

    return $lang_record_count;
}


/**
 * Print and add comment link
 * 
 * @param int $rid
 * @return string
 */
function dataplus_print_add_comment(){
    global $cm, $CFG;

    $ps = optional_param('ps', 0, PARAM_INT);
    $url = $CFG->wwwroot.'/mod/dataplus/view.php?id='.$cm->id.'&amp;mode=single&amp;changecomment='.dataplus_get_comment_form().'&amp;ps='.$ps.'#amendcomment';
    $lang_add_comment = get_string('addcomment', 'dataplus');

    return '<a href="'.$url.'">'.$lang_add_comment.'</a>';
}


/**
 * Returns an edit comment link
 * 
 * @return string
 */
function dataplus_print_edit_comment($cui){
    return dataplus_get_comment_link('edit',dataplus_get_comment_edit(),$cui,'#amendcomment');
}


/**
 * Returns a delete comment link
 * 
 * @return string
 */
function dataplus_print_delete_comment($cui){
    return dataplus_get_comment_link('delete',dataplus_get_comment_delete_form(),$cui,'#deletecomment');
}


/*
 * Returns a comment link
 * 
 * @return string
 */
function dataplus_get_comment_link($type,$typeno,$cui,$name = NULL){
    global $cm, $CFG;

    $ps = optional_param('ps', 0, PARAM_INT);
    $url = $CFG->wwwroot.'/mod/dataplus/view.php?id='.$cm->id.'&amp;mode=single&amp;changecomment='.$typeno.'&amp;cui='.$cui.'&amp;ps='.$ps.$name;

    $actions = dataplus_detail_supporting_actions();

    foreach ($actions as $action) {
        if ($action->name == $type) {
            $label = $action->label;
            break;
        }
    }

    return '<a href="'.$url.'"><img src="'.$CFG->wwwroot.'/pix/t/'.$type.'.gif" class="iconsmall" alt="'.$label.'" /></a>';
}


/**
 * Checks whether edit or delete icons should be printed in template output
 *
 * @param string $action
 * @param string $str
 * @param string $template
 * @param int $ui
 * @param int $creator_id
 * @param int $group_id
 * @param boolean $clear_actions
 */
function dataplus_create_record_icon($action,$str,$template,$ui,$creator_id,$group_id,$clear_actions = false){
    global $id, $CFG, $dataplus, $USER, $context, $mode, $groupmode, $currentgroup;

    if (!has_capability('mod/dataplus:dataeditown', $context) && $creator_id == $USER->id) {
        $clear_actions = true;
    }

    if (!has_capability('mod/dataplus:dataeditothers', $context) && $creator_id != $USER->id) {
        $clear_actions = true;
    }

    if ($groupmode>0 && $currentgroup>0 && !groups_is_member($currentgroup) && !has_capability('mod/dataplus:databaseedit', $context)) {
        $clear_actions = true;
    }

    $page_start = dataplus_get_page_start();

    $url = "view.php?id={$id}&amp;mode={$action}&amp;ui={$ui}&amp;ps={$page_start}&amp;oldmode={$mode}";

    if ($mode == 'searchresults') {
        $url .= "&rs=true";
    }

    if (!$clear_actions) {
        $rep = "<a title=\"{$str}\" href=\"{$url}\"><img src=\"{$CFG->wwwroot}/pix/t/{$action}.gif\" class=\"iconsmall\" alt=\"{$str}\" /></a>&nbsp;";
    } else {
        $rep = '';
    }

    return str_replace("**{$str}**",$rep,$template);
}


/**
 * Generates a 'more' link for templates.
 * @param int $ri
 * @param string $template
 * @return string
 */
function dataplus_create_more_link($ri,$template){
    global $id;

    $html = '<a class="dataplus_more_link" href="view.php?mode=single&amp;id='.$id.'&amp;ri='.$ri.'">'.get_string('more','dataplus').'</a>';

    return str_replace("**more**",$html,$template);
}


/**
 * Checks whether edit or delete icons should be printed in template output
 *
 * @param string $name
 * @param string $int
 * @param string $template
 * @return string
 */
function dataplus_create_supporting_information($name,$rec_no,$template){
    $template = str_replace("++record_no++",$rec_no,$template);

    return $template;
}


/**
 * Instantiates smarty template
 */
function dataplus_new_smarty(){
    global $smarty, $CFG;

    if (empty($smarty)) {
        require_once("{$CFG->libdir}/smarty/Smarty.class.php");

        $smarty = new Smarty;
        $smarty->template_dir = $CFG->libdir . '/smarty';
        $smarty->config_dir = $CFG->libdir . '/smarty';
        $smarty->cache_dir = $CFG->dataroot . '/temp';
        $smarty->compile_dir = $CFG->dataroot . '/temp';

        if (!file_exists($smarty->cache_dir)) {
            mkdir($smarty->cache_dir);
        }

        require_once("smarty/resource.string.php");
    }

    return $smarty;
}


/**
 * Removes escaping in results from MoodleForms
 * 
 * @param string $val
 * @return string
 */
function undo_escaping($val){
    $val = str_replace("\'", "'", $val);
    $val = str_replace('\"', '"', $val);
    return $val;
}


/**
 * Find the number of columns to allow for search orders.
 * 
 * @return int
 */
function dataplus_sort_order_limit(){
    global $dataplus_db;

    $parameters = dataplus_get_restricted_groups_parameters();

    $columns = $dataplus_db->list_dataplus_table_columns_array(true,$parameters);
    $no_cols = sizeof($columns);

    if ($no_cols>5) {
        $no_cols = 5;
    }

    return $no_cols;
}


/**
 * Convert a search string from the templates table to an object for use in queries
 * @param string $str
 */
function dataplus_create_sortarr_from_str($str){
    if (empty($str)) {
        return null;
    }

    $orders = explode(",",$str);

    for ($i=0; $i < sizeof($orders); $i++) {
        $order_parts = explode(" ", $orders[$i]);

        $arr[$order_parts[0]]->name = $order_parts[0];

        if (sizeof($order_parts) == 2) {
            $arr[$order_parts[0]]->sort = $order_parts[1];
        }
    }

    return $arr;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Mode helper functions                                                    //
//                                                                          //
////////////////////////////////////////////////////////////////////////////// 

/**
 * List of different edit modes
 * 
 * @return array
 */
function dataplus_get_edit_modes() {
    return array('edit','editsubmit','insert','insertbelowlimit');
}


/**
 * List of different search modes
 * 
 * @return array
 */
function dataplus_get_search_modes() {
    return array('search','searchadvanced','searchamend','searchresults');
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Commenting helper functions                                              //
//                                                                          //
////////////////////////////////////////////////////////////////////////////// 

/*
 * checks whether commenting can be used
 * 
 * @return boolean
 */
function dataplus_allow_comments(){
    global $dataplus, $mode;

    if ((!isset($dataplus->allowcomments) || $dataplus->allowcomments == 1) && $mode == 'single') {
        return true;
    }
    
    return false;
}


/*
 * get value representing action or output (comment applies to next 5 functions)
 * 
 * @return int
 */
function dataplus_get_comment_form(){
    return 1;
}


function dataplus_get_comment_amend(){
    return 2;
}


function dataplus_get_comment_edit(){
    return 3;
}

function dataplus_get_comment_delete_form(){
    return 4;
}

function dataplus_get_comment_delete(){
    return 5;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Commenting helper functions                                              //
//                                                                          //
////////////////////////////////////////////////////////////////////////////// 

/*
 * Find out if the user is impacted by a limit on the number of entries allowed
 * 
 * @return boolean
 */

function dataplus_maximum_entry_limit_reached(){
    global $dataplus_db, $dataplus;

    $entries = $dataplus_db->count_dataplus_database_query();

    if (!empty($dataplus->maxentries) && $entries >= $dataplus->maxentries) {
        return true;
    }

    $user_entries = $dataplus_db->count_user_entries();

    if (!empty($dataplus->maxentries) && $user_entries >= $dataplus->maxentriesperuser) {
        return true;
    }

    return false;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
// Querystring helper functions                                              //
//                                                                          //
////////////////////////////////////////////////////////////////////////////// 

/*
 * Returns an array of the querystring variables used in dataplus
 * 
 * @return array
 */
function dataplus_get_querystring_list(){
    return array(
        "id" => PARAM_TEXT,
        "ui" => PARAM_INT,
        "cui" => PARAM_INT,
        "mode" => PARAM_TEXT,
        "fid" => PARAM_INT,
        "changecomment" => PARAM_INT,
        "ps" => PARAM_INT,
        "group" => PARAM_TEXT,
        "reset" => PARAM_TEXT,
        "editor" => PARAM_TEXT,
        "ri" => PARAM_INT,
        "rs" => PARAM_TEXT,
        "view" => PARAM_TEXT,
        "oldmode" => PARAM_TEXT);
}


/*
 * Returns an array of the values of all querystring variables defined when the current page was called.
 * 
 * @return array
 */
function dataplus_get_querystring_vars(){
    $list = dataplus_get_querystring_list();

    $vals = array();

    foreach ($list as $l=>$v) {
        $var = optional_param($l, null, $v);

        if (!is_null($var)) {
            $vals[$l] = $var;
        }
    }

    return $vals;
}