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
$hostid = required_param('hostid', PARAM_INT);
$note = optional_param('note', null, PARAM_TEXT);
$sissection_idstring = required_param('sissection_idstring', PARAM_TEXT);

$sissection = get_record('enrol_mnetdb_coursesection', 'idstring', $sissection_idstring);
$strconfirm = get_string('confirm');
$strcancel = get_string('cancel');
$strsuccess = get_string('success');

$strareyousure = block_mnetdbenrol_get_string('areyousurecreaterequest', $sissection_idstring);

$confstr = md5(sesskey() . $sissection_idstring . $hostid);
if ($confirm != $confstr) {
    $displayusers = enrol_mnetdb_get_cs_users_display($sissection_idstring, false);
    print "
        <form method=\"post\" action=\"\">
            <input type=\"hidden\" name=\"sissection_idstring\" value=\"$sissection_idstring\" />
            <input type=\"hidden\" name=\"hostid\" value=\"$hostid\" />
            <input type=\"hidden\" name=\"confirm\" value=\"$confstr\" />
            <p>$strareyousure</p>
            <p><textarea name=\"note\"> </textarea></p>
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

if (!$rc = block_mnetdbenrol_createreq($sissection_idstring, $hostid, $note)) {
    error('Unable to request course creation. Please see your Moodle administrator.');
}

notify($strsuccess, 'notifysuccess');
redirect($CFG->wwwroot);

?>

