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

$confirm = optional_param('confirm', null, PARAM_ALPHANUM);
$createreqid = required_param('createreqid', PARAM_INT);

$request = get_record('block_mnetdbenrol_createreq', 'id', $createreqid);
$strconfirm = get_string('confirm');
$strcancel = get_string('cancel');
$strsuccess = get_string('success');
$strreasonforrequest = get_string('reasonforrequest', 'block_mnetdbenrol');

if (strlen($request->note)) {
    $request->note = $strreasonforrequest . ': ' . $request->note;
}

$strareyousure = block_mnetdbenrol_get_string('areyousurecreate', $request->coursesection_idstring, 0, $request->userid);

$confstr = md5(sesskey() . $createreqid);
if ($confirm != $confstr) {
    $displayusers = enrol_mnetdb_get_cs_users_display($request->coursesection_idstring, false);
    print "
        <form method=\"post\" action=\"\">
            <input type=\"hidden\" name=\"createreqid\" value=\"$createreqid\" />
            <input type=\"hidden\" name=\"confirm\" value=\"$confstr\" />
            <p>$strareyousure</p>
            <p>$request->note</p>
            <p>
                <input type=\"submit\" value=\"$strconfirm\" />
                <a href=\"$CFG->wwwroot\">$strcancel</a>
            </p>
        </form>
        <table style=\"text-align:center\">
        $displayusers
        </table>
    ";
    print_footer();
    exit();
}

if (!$rc = block_mnetdbenrol_coursecreate($createreqid)) {
    error('Unable to create course. Please see your Moodle administrator.');
}

notify($strsuccess, 'notifysuccess');
redirect("$CFG->wwwroot/course/edit.php?id=$rc");

?>

