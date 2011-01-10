<?php
// $Id$
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

global $CFG;
define('ENROL_MNETDB', 'mnetdb');
require_once($CFG->dirroot.'/enrol/enrol.class.php');

require_once("$CFG->dirroot/enrol/mnetdb/schemas/base.class.php");
if ($mnetdbschemas = get_list_of_plugins('enrol/mnetdb/schemas')) {
    foreach ($mnetdbschemas as $schema) {
        include_once("$CFG->dirroot/enrol/mnetdb/schemas/$schema/$schema.class.php");
    }
}

class enrolment_plugin_mnetdb {

    /*
     * For the given user, look in the MNet data mist for an authoritative list 
     * of enrolments, and then adjust the local Moodle assignments to match.
     */
    function setup_enrolments(&$user) {
        #error('MNet DB ' . __FUNCTION__ . ' still unimplemented.');
        return true;
    }

    /**
     * sync enrolments with mnet database, cache info locally
     *
     * @param object IGNORED FOR NOW: The role to sync for. If no role is 
     * specified, defaults are used.
     */
    function sync_enrolments($role = null) {
        global $CFG;
        error_reporting(E_ALL);

        // first, pack the sortorder...
        fix_course_sortorder();

        if (!$hosts = $this->mnetdb_hosts()) {
            return false;
        }
        foreach ($hosts as $host) {
            $schema = 'enrol_mnetdb_schema_' . $host->schema;
            $schema = new $schema();
            if (method_exists($schema, 'get_temp19_config')) {
                $host = $schema->get_temp19_config($host);
            }
            $schema->sync_enrolments($host);
        }
        enrol_mnetdb_effect_enrolments();

        return true;
    }

    /// Overide the get_access_icons() function
    function get_access_icons($course) {
    }


    # Put this off until we upgrade to 2.0. It all gets revamped.
    function config_form($frm) {
        global $CFG;
        error('No settings supported from here. Sorry!');
    }

    function mnet_publishes() {
        return array(array(
            'name'       => 'mnetdb',
            'apiversion' => 1,
            'methods'    => array(
                'sql_execute',
            ),
        ));
    }

    function mnetdb_hosts() {
        global $CFG;
        $app = get_record('mnet_application', 'name', 'mnetdb');
        if (!$hosts = get_records('mnet_host', 'applicationid', $app->id)) {
            return false;
        }
        # The plan is to support multiple instances of this enrolment plugin, 
        # and each may have some different settings. For now, pretend to have 
        # this plan but just get the settings from config.php...
        foreach ($hosts as &$host) {
            $host->schema = $CFG->enrol_mnetdb_schema;
        }
        return $hosts;
    }
} // end of class

function enrol_mnetdb_sql_execute($hostid, $sql) {
    global $CFG, $USER, $MNET;
    require_once $CFG->dirroot . '/mnet/xmlrpc/client.php';

    if (debugging('', DEBUG_ALL)) {
        print "sql: $sql\n";
        flush();
    }

    // get the Service Provider info
    $mnet = new mnet_peer();
    $mnet->set_id($hostid);

    // set up the RPC request
    $mnetrequest = new mnet_xmlrpc_client();
    $mnetrequest->set_method('enrol/mnetdb/enrol.php/sql_execute');
    $mnetrequest->add_param($sql);

    // Initialise $message
    $message = '';

    // Thunderbirds are go! Do RPC call and store response
    if ($mnetrequest->send($mnet) === true) {
        $results = array();
        foreach ($mnetrequest->response as $row) {
            for ($i=0; $i<count($row); $i++) {
                if (is_string($row[$i])) {
                    $row[$i] = base64_decode($row[$i]);
                }
            }
            $results[] = $row;
        }
        return $results;
    } else {
        foreach ($mnetrequest->error as $errormessage) {
            list($code, $errormessage) = array_map('trim',explode(':', $errormessage, 2));
            $message .= "ERROR $code:<br/>$errormessage<br/>";
        }
        print "MNet Auth error: $message";
    }
    return false;
}

function enrol_mnetdb_sql_asobj($hostid, $fields, $sql) {
    if (!$rows = enrol_mnetdb_sql_execute($hostid, $sql)) {
        return false;
    }
    $results = array();
    foreach ($rows as $row) {
        $o = new stdclass;
        for ($i=0; $i<count($fields); $i++) { $o->$fields[$i] = $row[$i]; }
        $results[] = $o;
    }
    return $results;
}

function enrol_mnetdb_coursesectionmap($courseid, $sissection_idstring, $hostid) {
    $record = new StdClass;
    $record->coursesection_idstring = $sissection_idstring;
    $record->mdlcourseid = $courseid;
    $record->hostid = $hostid;
    if (insert_record('enrol_mnetdb_coursesectionmap', $record)) {
        enrol_mnetdb_effect_enrolments();
    }
    return true;
}

function enrol_mnetdb_courseunmap($courseid, $sissection_idstring, $hostid) {
    if (delete_records('enrol_mnetdb_coursesectionmap', 'coursesection_idstring', $sissection_idstring, 'mdlcourseid', $courseid, 'hostid', $hostid)) {
        enrol_mnetdb_effect_enrolments();
    }
}

function enrol_mnetdb_get_cs_users($sissection_idstring, $include_teachers=true, $include_students=true) {
    $androles = '';
    if (!$include_teachers or !$include_students) {
        if ($include_teachers) {
            $androles .= ' AND isteacher = 1 ';
        }
        if ($include_students) {
            $androles .= ' AND isstudent = 1 ';
        }
    }
    $sisusers = array();
    $where = " coursesection_idstring = '$sissection_idstring' $androles ";
    if ($cs_users = get_records_select('enrol_mnetdb_cs_user', $where)) {
        foreach ($cs_users as $cs_user) {
            if ($sisuser = get_record('enrol_mnetdb_user', 'idstring', $cs_user->user_idstring)) {
                $sisusers[] = $sisuser;
            }
        }
    }
    return $sisusers;
}

function enrol_mnetdb_get_cs_users_display($sissection_idstring, $include_teachers=true, $include_students=true) {
    global $CFG;
    $strheading = get_string('affectedusers', 'block_mnetdbenrol');
    $csusers = array();
    $count = 0;
    $dispusers = get_string('none');
    if ($tmp = enrol_mnetdb_get_cs_users($sissection_idstring, $include_teachers, $include_students)) {
        $dispusers = '';
        foreach ($tmp as $csuser) {
            $count++;
            if ($user = get_record('user', 'username', $csuser->username)) {
                $link = "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id\">".fullname($user)."</a>";
                $key = "$user->lastname $user->firstname";
            } else {
                # Maybe this csuser doesn't have a Moodle account yet.
                $key = $link = $csuser->username;
            }
            $csusers[$key] = "<tr><td colspan=\"10\">$link</td></tr>";
        }
    }
    ksort($csusers);
    foreach($csusers as $csuser) {
        $dispusers .= $csuser;
    }

    return "
        <tr>
            <td colspan=\"10\">
                <big><b>$strheading($count)</b></big>
            </td>
        </tr>
        " . $dispusers;
}

# Later we can allow configurability of the default names.
function enrol_mnetdb_coursecreate_fullname($sissection) {
    $tchname = '';
    if ($sisusers = enrol_mnetdb_get_cs_users($sissection->idstring, true, false)) {
        foreach ($sisusers as $sisuser) {
            $tch = get_record('user', 'username', $sisuser->username);
            $tchname = "$tch->lastname - ";
            # This is hackish, but for now just use the first one we find.
            break;
        }
    }
    return "$tchname$sissection->displayname";
}

function enrol_mnetdb_effect_enrolments($courseid='') {
    global $CFG;

    # Prune old mnetdb enrolments in all courses.
    $courses = get_records('course');
    foreach ($courses as $course) {
        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        # Keep in mind that we could have enrolments in this Moodle course from 
        # *other* mnetdb courses, so the nested select needs to catch those.
        #
        # TODO: Do not prune teacher enrolments this way yet. (Otherwise they 
        # could lock themselves out of their courses by using the MNet DB 
        # Enrolment Block.)
        $prunes = get_records_sql("
            SELECT u.id, ra.*, r.shortname
            FROM {$CFG->prefix}role_assignments ra
                JOIN {$CFG->prefix}user u ON u.id = ra.userid
                JOIN {$CFG->prefix}role r ON r.id = ra.roleid
            WHERE ra.contextid = {$context->id}
                AND ra.enrol = '".ENROL_MNETDB."'
                AND r.shortname != 'editingteacher'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$CFG->prefix}enrol_mnetdb_user mu
                        JOIN {$CFG->prefix}enrol_mnetdb_cs_user csu ON csu.user_idstring = mu.idstring
                        JOIN {$CFG->prefix}enrol_mnetdb_coursesectionmap cm ON cm.coursesection_idstring = csu.coursesection_idstring
                    WHERE cm.mdlcourseid = $course->id
                        AND mu.username = u.username
                )
            ");
        if ($prunes) {
            foreach ($prunes as $role_assignment) {
                if (role_unassign($role_assignment->roleid, $role_assignment->userid, 0, $role_assignment->contextid)){
                    error_log( "Unassigned {$role_assignment->shortname} assignment #{$role_assignment->id} for course {$course->id} (" . format_string($course->shortname) . "); user {$role_assignment->userid}");
                } else {
                    error_log( "Failed to unassign {$role_assignment->shortname} assignment #{$role_assignment->id} for course {$course->id} (" . format_string($course->shortname) . "); user {$role_assignment->userid}");
                }
            }
        }
    }

    if (!$maps = get_records('enrol_mnetdb_coursesectionmap')) {
        return;
    }

    # Insert new mnetdb enrolments.
    foreach ($maps as $map) {
        $course = get_record('course', 'id', $map->mdlcourseid);
        if (!is_object($course)) {
            error_log( "Moodle course ($map->mdlcourseid) does not exist, skipping.");
        }
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        # Enrolments to insert.
        $newenrolments = get_records_sql("
            SELECT u.id AS userid, csu.*
            FROM {$CFG->prefix}enrol_mnetdb_cs_user csu
                JOIN {$CFG->prefix}enrol_mnetdb_user mu ON mu.idstring = csu.user_idstring 
                JOIN {$CFG->prefix}user u ON u.username = mu.username
            WHERE csu.coursesection_idstring = '$map->coursesection_idstring'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$CFG->prefix}role_assignments ra
                        JOIN {$CFG->prefix}role r ON r.id = ra.roleid
                    WHERE ra.contextid = {$context->id}
                        AND ra.userid = u.id
                        AND ((r.shortname = 'editingteacher' AND csu.isteacher = 1) OR (r.shortname = 'student' AND csu.isstudent = 1))
                )
            ");
        $role_teacher = get_record('role', 'shortname', 'editingteacher');
        $role_student = get_record('role', 'shortname', 'student');

        # Effect current enrolments.
        if ($newenrolments) {
            foreach ($newenrolments as $sisenrolment) {
                $role = $role_student;
                if ($sisenrolment->isteacher) {
                    $role = $role_teacher;
                }
                if (role_assign($role->id, $sisenrolment->userid, 0, $context->id, 0, 0, 0, ENROL_MNETDB)){
                    error_log( "Assigned role {$role->shortname} to user {$sisenrolment->userid} in course {$course->id} (" . format_string($course->shortname) . ")");
                } else {
                    error_log( "Failed to assign role {$role->shortname} to user {$sisenrolment->userid} in course {$course->id} (" . format_string($course->shortname) . ")");
                }
            }
        }
    }
}

?>
