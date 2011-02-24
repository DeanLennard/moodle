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
require_once("$CFG->libdir/rsslib.php");
require_once("$CFG->dirroot/course/lib.php");

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record("course", "id", $id)) {
    print_error("Course ID is incorrect");
}

require_course_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

add_to_log($course->id, "dataplus", "view all", "index.php?id=$course->id", "");

$str_dataplus = get_string("modulename", "dataplus");
$str_rss = get_string("rss");
$str_name = get_string("name");
$str_week = get_string("week");
$str_topic = get_string("topic");
$str_size = get_string("size", "dataplus");

$navlinks = array();
$navlinks[] = array(
    'name' => $str_dataplus, 
    'link' => "index.php?id=$course->id", 
    'type' => 'activity');
$navigation = build_navigation($navlinks);

print_header_simple($str_dataplus, '', $navigation, '', '', true, '', navmenu($course));

if (!$datapluss = get_all_instances_in_course("dataplus", $course)) {
    notice(get_string('thereareno', 'moodle', $str_dataplus), "../../course/view.php?mode=view&amp;id=$course->id");
    die;
}

$table = new html_table();

if ($course->format == "weeks") {
    $table->head  = array ($str_week, $str_name, $str_size);
    $table->align = array ("CENTER", "LEFT", "CENTER");
} else if ($course->format == "topics") {
    $table->head  = array ($str_topic, $str_name, $str_size);
    $table->align = array ("CENTER", "LEFT", "CENTER");
} else {
    $table->head  = array ($str_name, $str_size);
    $table->align = array ("LEFT", "CENTER");
}

$rss = (!empty($CFG->enablerssfeeds) && !empty($CFG->data_enablerssfeeds));

if ($rss) {
    require_once($CFG->libdir."/rsslib.php");
    array_push($table->head, 'RSS');
    array_push($table->align, 'center');
}

$currentsection = "";

foreach ($datapluss as $dataplus) {
    if (!$dataplus->visible && has_capability('moodle/course:viewhiddenactivities', $context)) {
        // Show dimmed if the mod is hidden.
        $link = "<a class=\"dimmed\" href=\"view.php?mode=view&amp;id=$dataplus->coursemodule\">".format_string($dataplus->name,true)."</a>";
    } else if ($dataplus->visible) {
        // Show normal if the mod is visible.
        $link = "<a href=\"view.php?mode=view&amp;id=$dataplus->coursemodule\">".format_string($dataplus->name,true)."</a>";
    } else {
        // Don't show the glossary.
        continue;
    }

    $printsection = "";

    if ($dataplus->section !== $currentsection) {
        if ($dataplus->section) {
            $printsection = $dataplus->section;
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $dataplus->section;
    }

    if ($rss && $data->rssarticles > 0) {
        $rsslink = '';
        $rsslink = rss_get_link($course->id, $USER->id, 'data', $data->id, 'RSS');
    }

    $filepath = $CFG->dataroot.'/'.$id.'/moddata/dataplus/'.$dataplus->id.'/'.$dataplus->id.'.sqlite';

    if (file_exists($filepath)) {
        $file_size = filesize($filepath);
        $size = round($file_size/1000).'k';
    } else {
        $size = '0k';
    }

    if ($course->format == "weeks" or $course->format == "topics") {
        $linedata = array ($printsection, $link, $size);
    } else {
        $linedata = array ($link, $size);
    }

    if ($rss) {
        array_push($row, $rsslink);
    }

    $table->data[] = $linedata;
}

echo "<br />";
echo html_writer::table($table);
echo $OUTPUT->footer();