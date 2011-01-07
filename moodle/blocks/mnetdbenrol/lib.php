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


define('ENROL_MNETDB_CMD_MAP', 'map');
define('ENROL_MNETDB_CMD_UNMAP', 'unmap');

function block_mnetdbenrol_sissections() {
    global $USER;
    $sissections = array();
    if (!$sisuser = get_record('enrol_mnetdb_user', 'username', $USER->username)) {
        return $sissections;
    }
    if (!$relations = get_records('enrol_mnetdb_cs_user', 'user_idstring', $sisuser->idstring)) {
        return $sissections;
    }
    foreach ($relations as $relation) {
        if (!$relation->isteacher) {
            # we aren't doing anything with students on this page yet
            continue;
        }
        if ($coursesections = get_records('enrol_mnetdb_coursesection', 'idstring', $relation->coursesection_idstring, 'displayname')) {
            foreach ($coursesections as $s) {
                $sissections[$s->displayname] = $s;
            }
        }
    }
    ksort($sissections);
    return $sissections;
}

function block_mnetdbenrol_coursesectionmaps($objects, $objtype='sissections', $maps='') {
    if (!is_object($maps)) {
        $maps = new StdClass;
        $maps->s2m = array();
        $maps->m2s = array();
    }
    $field = 'coursesection_idstring';
    $attribute = 'idstring';
    if ($objtype == 'mdlcourses') {
        $field = 'mdlcourseid';
        $attribute = 'id';
    }
    foreach ($objects as $obj) {
        if ($tmp = get_records('enrol_mnetdb_coursesectionmap', $field, $obj->$attribute)) {
            foreach ($tmp as $t) {
                if (!isset($maps->s2m[$t->coursesection_idstring])) {
                    $maps->s2m[$t->coursesection_idstring] = array();
                }
                $maps->s2m[$t->coursesection_idstring][$t->mdlcourseid] = 1;

                if (!isset($maps->m2s[$t->mdlcourseid])) {
                    $maps->m2s[$t->mdlcourseid] = array();
                }
                $maps->m2s[$t->mdlcourseid][$t->coursesection_idstring] = 1;
            }
        }
    }
    return $maps;
}

function block_mnetdbenrol_mdlcourses() {
    global $USER;
    if (isset($USER->access)) {
        $accessinfo = $USER->access;
    } else {
        $accessinfo = get_user_access_sitewide($USER->id);
    }
    if (!isset($USER->block_mnetdbenrol_mdlcourses)) {
        $USER->block_mnetdbenrol_mdlcourses = new StdClass;
        $USER->block_mnetdbenrol_mdlcourses->byname = array();
        $USER->block_mnetdbenrol_mdlcourses->bynum = array();
        $courses = get_user_courses_bycap($USER->id, 'moodle/role:assign', $accessinfo, false, '', array('id', 'fullname', 'idnumber'));
        foreach ($courses as $k => $v) {
            $USER->block_mnetdbenrol_mdlcourses->byname[$v->fullname] = &$courses[$k];
            $USER->block_mnetdbenrol_mdlcourses->bynum[$v->id] = &$courses[$k];
        }
    }
    return $USER->block_mnetdbenrol_mdlcourses;
}

function block_mnetdbenrol_managemaps_context($course) {
    global $CFG;
    if ($course->id == SITEID) { return block_mnetdbenrol_managemaps_site(); }
    if (!has_capability('moodle/role:assign', get_context_instance(CONTEXT_COURSE, $course->id))) {
        return '';
    }

    $mapped = get_string('connected', 'block_mnetdbenrol').':<br />';
    $unmapped = get_string('disconnected', 'block_mnetdbenrol').':<br />';
    $sissections = block_mnetdbenrol_sissections();
    $maps = block_mnetdbenrol_coursesectionmaps($sissections);
    $url = new moodle_url("$CFG->wwwroot/blocks/mnetdbenrol/coursemap.php");
    $url->param('courseid', $course->id);
    foreach ($sissections as $sissection) {
        $url->param('sissection_idstring', $sissection->idstring);
        $url->param('hostid', $sissection->hostid);
        if (isset($maps->s2m[$sissection->idstring]) and isset($maps->s2m[$sissection->idstring][$course->id]) and $maps->s2m[$sissection->idstring][$course->id]) {
            $url->param('cmd', ENROL_MNETDB_CMD_UNMAP);
            $mapped .= "<a href=\"".$url->out()."\">$sissection->displayname</a><br />\n";
        } else {
            $url->param('cmd', ENROL_MNETDB_CMD_MAP);
            $unmapped .= "<a href=\"".$url->out()."\">$sissection->displayname</a><br />\n";
        }
    }
    return $mapped.$unmapped;
}

function block_mnetdbenrol_createreq_list() {
    global $CFG;
    if (!has_capability('moodle/course:create', get_context_instance(CONTEXT_COURSE, SITEID))) {
        return '';
    }
    $output = '';
    if (!$requests = get_records('block_mnetdbenrol_createreq')) {
        return $output;
    }
    foreach ($requests as $request) {
        $user = get_record('user', 'id', $request->userid);
        $fullname = fullname($user);
        $sissection = get_record('enrol_mnetdb_coursesection', 'idstring', $request->coursesection_idstring);
        $url = new moodle_url("$CFG->wwwroot/blocks/mnetdbenrol/coursecreate.php");
        $url->param('createreqid', $request->id);
        $url = $url->out();
        $output .= "<li><a href=\"$url\">$sissection->displayname ($fullname)</a></ul>\n";
    }
    if (!empty($output)) {
        $output = get_string('requestednewcourses', 'block_mnetdbenrol') . ":<ul>$output</ul>";
    }
    return $output;
}

function block_mnetdbenrol_managemaps_site() {
    global $CFG;

    $requested = '';
    $unmapped = '';
    $sissections = block_mnetdbenrol_sissections();
    $maps = block_mnetdbenrol_coursesectionmaps($sissections);
    foreach ($sissections as $sissection) {
        $url = new moodle_url("$CFG->wwwroot/blocks/mnetdbenrol/coursecreatereq.php");
        $url->param('sissection_idstring', $sissection->idstring);
        $url->param('hostid', $sissection->hostid);
        $url = $url->out();
        if (!isset($maps->s2m[$sissection->idstring]) or !$maps->s2m[$sissection->idstring]) {
            if (get_record('block_mnetdbenrol_createreq', 'coursesection_idstring', $sissection->idstring)) {
                $requested .= "<li>$sissection->displayname</li>";
            } else {
                $unmapped .= "<li><a href=\"$url\">$sissection->displayname</a></li>\n";
            }
        }
    }
    $output = block_mnetdbenrol_createreq_list();
    if (!empty($unmapped)) {
        $output .= get_string('requestnewcourse', 'block_mnetdbenrol');
        $output .= ":<ul>$unmapped</ul>";
    }
    if (!empty($requested)) {
        $output .= get_string('requestednewcourse', 'block_mnetdbenrol');
        $output .= ":<ul>$requested</ul>";
    }
    return $output;
}

function block_mnetdbenrol_createreq($coursesection_idstring, $hostid, $note=null) {
    global $USER;
    if (!$sisuser = get_record('enrol_mnetdb_user', 'username', $USER->username)) {
        return false;
    }
    $where = " coursesection_idstring = '$coursesection_idstring' AND isteacher = 1 AND user_idstring = '$sisuser->idstring' ";
    if (!$cs_users = get_records_select('enrol_mnetdb_cs_user', $where)) {
        return false;
    }
    $req = new StdClass;
    $req->coursesection_idstring = $coursesection_idstring;
    $req->hostid = $hostid;
    $req->note = $note;
    $req->userid = $USER->id;
    $req->timecreated = time();
    $req->timemodified = time();
    return insert_record('block_mnetdbenrol_createreq', $req);
}

function block_mnetdbenrol_get_string($stringkey, $sissection_idstring='', $courseid=0, $userid=0) {
    global $CFG;
    $string = get_string($stringkey, 'block_mnetdbenrol');

    $strsisname = strlen($CFG->block_mnetdbenrol_sisname) ? $CFG->block_mnetdbenrol_sisname : get_string('configsisname_default', 'block_mnetdbenrol');
    $string = preg_replace('/KEY_SISNAME/', $strsisname, $string);

    if (!empty($sissection_idstring)) {
        $sissection = get_record('enrol_mnetdb_coursesection', 'idstring', $sissection_idstring);
        $string = preg_replace('/KEY_SISSECTION/', $sissection->displayname, $string);
    }

    if ($courseid) {
        $course = get_record('course', 'id', $courseid);
        $string = preg_replace('/KEY_MDLCOURSE/', $course->fullname, $string);
    }

    if ($userid) {
        $user = get_record('user', 'id', $userid);
        $string = preg_replace('/KEY_USERFULLNAME/', fullname($user), $string);
    }

    return $string;
}

function block_mnetdbenrol_coursecreate($createreqid) {
    global $CFG;
    if (!has_capability('moodle/course:create', get_context_instance(CONTEXT_COURSE, SITEID))) {
        error('You do not have permission to create a course.');
    }
    $request = get_record('block_mnetdbenrol_createreq', 'id', $createreqid);
    $sissection = get_record('enrol_mnetdb_coursesection', 'idstring', $request->coursesection_idstring);

    if(!empty($CFG->enrol_mnetdb_template)){
        $course = get_record("course", 'shortname', $CFG->enrol_mnetdb_template);
    } else {
        $site = get_site();
        $course = new StdClass;
        $course->startdate = time() + 3600 * 24;
        $course->summary = get_string("defaultcoursesummary");
        $course->format = "weeks";
        $course->password = "";
        $course->guest = 0;
        $course->numsections = 10;
        $course->idnumber = '';
        $course->cost = '';
        $course->newsitems = 5;
        $course->showgrades = 1;
        $course->groupmode = 0;
        $course->groupmodeforce = 0;
        $course->student = $site->student;
        $course->students = $site->students;
        $course->teacher = $site->teacher;
        $course->teachers = $site->teachers;
    }
    $course->fullname = enrol_mnetdb_coursecreate_fullname($sissection);
    $course->shortname = preg_replace('/ /', '', $course->fullname);

    $catmin = current(get_records('course_categories', '', '', 'id', 'id', 0, 1));
    $course->category = $catmin->id;     // the misc 'catch-all' category...unless it's been deleted
    if (!empty($CFG->enrol_mnetdb_category)){ //category = 0 or undef will break moodle
        $course->category = $CFG->enrol_mnetdb_category;
    }

    // define the sortorder
    $sort = get_field_sql('SELECT COALESCE(MAX(sortorder)+1, 100) AS max ' .
                          ' FROM ' . $CFG->prefix . 'course ' .
                          ' WHERE category=' . $course->category);
    $course->sortorder = $sort;

    // override with local data
    $course->startdate   = time() + 3600 * 24;
    $course->timecreated = time();
    $course->visible     = 1;

    // clear out id just in case
    unset($course->id);

    // truncate a few key fields
    $course->idnumber  = substr($course->idnumber, 0, 100);
    $course->shortname = substr($course->shortname, 0, 100);

    // store it and log
    if ($newcourseid = insert_record("course", addslashes_object($course))) {  // Set up new course
        $section = NULL;
        $section->course = $newcourseid;   // Create a default section.
        $section->section = 0;
        $section->id = insert_record("course_sections", $section);
        $page = page_create_object(PAGE_COURSE_VIEW, $newcourseid);
        blocks_repopulate_page($page); // Return value no

        fix_course_sortorder();
        add_to_log($newcourseid, "course", "new", "view.php?id=$newcourseid", "enrol/mnetdb requested creation");
    } else {
        trigger_error("Could not create requested course: $createreqid");
        notify("Yikes! Serious error! Could not create the new course!");
        return false;
    }

    enrol_mnetdb_coursesectionmap($newcourseid, $sissection->idstring, $sissection->hostid);

    delete_records('block_mnetdbenrol_createreq', 'id', $createreqid);

    return $newcourseid;
}

?>
