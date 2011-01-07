<?php
/********************************************************************************
 * index.php
 *
 * @copyright 2010, Matt Oquist ({@link http://majen.net})
 * @copyright 2010, SAU16 ({@link http://sau16.org})
 * @author Matt Oquist
 * @version  $Id$
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * vim:shiftwidth=4
 ********************************************************************************/
global $CFG, $USER;

require_once('../../config.php');
require_once('lib.php');

$courseid = optional_param('courseid', null, PARAM_INT);
$cmd = optional_param('cmd', null, PARAM_ALPHANUM);

$site = get_site();
$strheading = strlen($CFG->block_mnetdbenrol_blockname) ? $CFG->block_mnetdbenrol_blockname : get_string('configblockname_default', 'block_mnetdbenrol');
$nav = array();
if ($courseid) {
    $course = get_record('course', 'id', $courseid);
    $url = "$CFG->wwwroot/course/view.php?id=$courseid";
    $nav[] = array( 'name' => $course->fullname, 'link' => $url);
}
$nav[] = array( 'name' => $strheading, 'link' => '');
print_header("$site->shortname: $strheading", $site->fullname, build_navigation($nav));

$sissections = block_mnetdbenrol_sissections();
$maps = block_mnetdbenrol_coursesectionmaps($sissections);
$mdlcourses = block_mnetdbenrol_mdlcourses();

print_r($maps);
print "<br />\n";

# TODO: update to use Moodle 2.0 renderer stuff! Someday!
$rows = array();
foreach ($sissections as $section) {
    $baseurl = "$CFG->wwwroot/blocks/mnetdbenrol/index.php?sissection_idstring=$section->idstring";
    $url_connect = "<a href=\"\">".get_string('connect', 'block_mnetdbenrol')."</a>";
    $rows[] = "
        <tr>
            <td></td>
            <td colspan=\"3\">$section->displayname ($section->idstring)</td>
            <td>$url_connect</td>
            <td></td>
            <td></td>
        </tr>
        ";
    if (isset($maps->s2m[$section->idstring])) {
        print "bleef<br />\n";
        foreach (array_keys($maps->s2m[$section->idstring]) as $mdlcourseid) {
            $course = $mdlcourses->bynum[$mdlcourseid];
            $url_course = "$CFG->wwwroot/course/view.php?id=$course->id";
            $url_course = "<a target=\"_blank\" href=\"$url_course\">$course->fullname</a>";
            $rows[] = "
                    <tr>
                        <td></td>
                        <td>&nbsp;&nbsp;</td>
                        <td>$url_course</td>
                        <td>$url_disconnect</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    ";
        }
    }
}

print "
    <table border=\"1\" cellpadding=\"3\">
        <tr>
            <td></td>
            <td colspan=\"3\">".get_string('name')."</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    ";
foreach ($rows as $row) { print $row; }
print "</table>";



print_r($mdlcourses);

?>
