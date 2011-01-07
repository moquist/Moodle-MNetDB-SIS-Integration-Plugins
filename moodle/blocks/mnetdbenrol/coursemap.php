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

// @copyright 2010, Matt Oquist ({@link http://majen.net})
// @copyright 2010, SAU16 ({@link http://sau16.org})
// @author Matt Oquist
// @version  $Id$
// @license http://www.gnu.org/copyleft/gpl.html GNU Public License
// vim:shiftwidth=4

global $CFG, $USER;

require_once('../../config.php');
require_once('../../enrol/mnetdb/enrol.php');
require_once('../../lib/weblib.php');
require_once('lib.php');
require_once('head.php');

$courseid = required_param('courseid', PARAM_INT);
$cmd = required_param('cmd', PARAM_ALPHANUM);
$confirm = optional_param('confirm', null, PARAM_ALPHANUM);
$hostid = required_param('hostid', PARAM_INT);
$sissection_idstring = required_param('sissection_idstring', PARAM_TEXT);

$sissection = get_record('enrol_mnetdb_coursesection', 'idstring', $sissection_idstring);
$course = get_record('course', 'id', $courseid);
$strconfirm = get_string('confirm');
$strcancel = get_string('cancel');
#$strsuccess = get_string('submissionsaved', 'block_mnetdbenrol');
$strsuccess = get_string('success');

switch ($cmd) {
case ENROL_MNETDB_CMD_MAP:
    $strareyousure = block_mnetdbenrol_get_string('areyousuremap', $sissection_idstring, $courseid);
    break;
case ENROL_MNETDB_CMD_UNMAP:
    $strareyousure = block_mnetdbenrol_get_string('areyousureunmap', $sissection_idstring, $courseid);
    break;
default:
    print get_string('unknowncommand', 'block_mnetdbenrol');
}

$confstr = md5(sesskey() . $courseid . $sissection_idstring . $hostid . $cmd);
if ($confirm != $confstr) {
    $url = new moodle_url("$CFG->wwwroot/blocks/mnetdbenrol/coursemap.php");
    $url->param('cmd', $cmd);
    $url->param('courseid', $courseid);
    $url->param('sissection_idstring', $sissection_idstring);
    $url->param('hostid', $hostid);
    $url->param('confirm', $confstr);

    $displayusers = enrol_mnetdb_get_cs_users_display($sissection_idstring, false);

    print "
        <table style=\"text-align:center\">
            <tr><td colspan=\"2\">
                $strareyousure
            </td></tr>
            <tr>
                <td>
                    <a href=\"".$url->out()."\"><big><big>$strconfirm</big></big></a>
                </td>
                <td>
                    <a href=\"$CFG->wwwroot/course/view.php?id=$courseid\"><big><big>$strcancel</big></big></a>
                </td>
            </tr>
            $displayusers
        </table>
    ";
    print_footer();
    exit();
}

switch ($cmd) {
case ENROL_MNETDB_CMD_MAP:
    if (!$rc = enrol_mnetdb_coursesectionmap($courseid, $sissection_idstring, $hostid)) {
        error('Unable to connect course. Please see your Moodle administrator.');
    }
    break;
case ENROL_MNETDB_CMD_UNMAP:
    $rc = enrol_mnetdb_courseunmap($courseid, $sissection_idstring, $hostid);
    break;
# default covered above in previous switch statement
}

notify($strsuccess, 'notifysuccess');
redirect("$CFG->wwwroot/course/view.php?id=$courseid");

?>

