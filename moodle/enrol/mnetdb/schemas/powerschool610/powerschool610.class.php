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


class enrol_mnetdb_schema_powerschool610 extends enrol_mnetdb_schema_base {
    /**
     * Ensure there are no local idstring collisions.
     * This is necessary because PS stores the teachers and students in
     * different tables, so the IDs may collide.
     * This does imply that no single user in a PS integration can be both
     * a teacher and a student.
     */
    protected function idstring_2local($idstring, $isteacher=false) {
        if ($isteacher) {
            return 'tch:' . $idstring;
        }
        return 'stu:' . $idstring;
    }

    /**
     * Restore the remote idstring for remote queries.
     */
    protected function idstring_2remote($idstring) {
        return preg_replace('/^tch:|^stu:/', '', $idstring);
    }

    protected function get_users($host, $username='') {
        $schoolids = '';
        if (isset($host->schoolids)) {
            $schoolids = implode(',', $host->schoolids);
            $schoolids = " AND schoolid IN ($schoolids) ";
        }
        $susername = $tusername = '';
        if (strlen($username)) {
            $susername = " AND student_web_id = '$username' ";
            $tusername = " AND teacherloginid = '$username' ";
        }
        $users = enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'username', 'hostid', 'isteacher', 'isstudent'),
            "SELECT dcid, student_web_id, $host->id, 0, 1 FROM ps.students WHERE 1=1 $schoolids $susername
             UNION SELECT dcid, teacherloginid, $host->id, 1, 0 FROM ps.teachers WHERE 1=1 $schoolids $tusername");
        if (!$users) {
            return false;
        }
        foreach ($users as &$user) {
            $user->username = strtolower($user->username);
            $user->idstring = $this->idstring_2local($user->idstring, $user->isteacher);
        }
        return $users;
    }

    protected function get_courses($host) {
        $schoolids = '';
        if (isset($host->schoolids)) {
            $schoolids = implode(',', $host->schoolids);
            $schoolids = " AND schoolid IN ($schoolids) ";
        }
        return enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'name', 'hostid'),
            "SELECT dcid, course_name, $host->id FROM ps.courses WHERE 1=1 $schoolids"
        );
    }

    protected function get_coursesections($host) {
        $schoolids = '';
        if (isset($host->schoolids)) {
            $schoolids = implode(',', $host->schoolids);
            $schoolids = " AND ps.sections.schoolid IN ($schoolids) ";
        }
        # PowerSchool 6.10 stores section-teacher associations here, so grab the teacher ID.
        # Also see cache_coursesections() below.
        $sql = "
            SELECT ps.sections.dcid, ps.courses.dcid, ps.teachers.dcid, ps.section_meeting.meeting, ps.courses.course_name, $host->id
            FROM ps.sections
            JOIN ps.courses ON ps.courses.course_number = ps.sections.course_number AND ps.courses.schoolid = ps.sections.schoolid
            JOIN ps.teachers ON ps.sections.teacher = ps.teachers.id AND ps.sections.schoolid = ps.teachers.schoolid
            JOIN ps.section_meeting ON ps.sections.id = ps.section_meeting.sectionid AND ps.sections.schoolid = ps.section_meeting.schoolid
            WHERE 1=1 $schoolids
            AND ps.sections.termid IN (SELECT id FROM ps.terms WHERE schoolid = ps.sections.schoolid
            AND firstday <= (SELECT SYSDATE + $this->term_window FROM DUAL)
            AND lastday >= (SELECT SYSDATE - $this->term_window FROM DUAL))
            ";
        # Get all the 'sections' in the current term.
        $sections = enrol_mnetdb_sql_asobj($host->id, array('idstring', 'course_idstring', 'user_idstring', 'meetingname', 'course_name', 'hostid'), $sql);
        foreach ($sections as &$section) {
            # Put the displayname together during the initial data query -- this
            # seems simpler (for now) than providing a plugin-specific interface to
            # fetch an SIS-specific display name later.
            $section->displayname = "$section->meetingname $section->course_name";
            $section->user_idstring = $this->idstring_2local($section->user_idstring, true);
        }
        return $sections;
    }
    # PowerSchool 6.10 stores the teacher ID with the coursesection, so we need 
    # to override this method and handle that data.
    protected function cache_coursesections($coursesections) {
        # First do the usual, then handle the teacher-coursesection maps.
        enrol_mnetdb_schema_base::cache_coursesections($coursesections);
        begin_sql();
        foreach ($coursesections as $coursesection) {
            $coursesection = addslashes_object($coursesection);
            $enrolment = new StdClass;
            $enrolment->isteacher = 1;
            $enrolment->coursesection_idstring = $coursesection->idstring;
            $enrolment->user_idstring = $coursesection->user_idstring;
            $enrolment->hostid = $coursesection->hostid;
            if (!$t = get_record('enrol_mnetdb_cs_user', 'coursesection_idstring', $enrolment->coursesection_idstring, 'user_idstring', $enrolment->user_idstring, 'hostid', $enrolment->hostid)) {
                insert_record('enrol_mnetdb_cs_user', $enrolment);
            } else {
                $enrolment->id = $t->id;
                update_record('enrol_mnetdb_cs_user', $enrolment);
            }
        }
        commit_sql();
    }


    protected function get_enrolments($host) {
        $schoolids = '';
        if (isset($host->schoolids)) {
            $schoolids = implode(',', $host->schoolids);
            $schoolids = " AND ps.cc.schoolid IN ($schoolids) ";
        }
        # Get all the 'sections' in the current term.
        $enrolments = enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'user_idstring', 'coursesection_idstring', 'hostid', 'isstudent'), "
            SELECT ps.cc.dcid, ps.cc.studentid, ps.sections.dcid, $host->id, 1
            FROM ps.cc
            JOIN ps.sections ON ps.sections.id = ps.cc.sectionid AND ps.sections.schoolid = ps.cc.schoolid
            WHERE 1=1 $schoolids
            AND ps.cc.termid IN (
                SELECT id
                FROM ps.terms
                WHERE schoolid = ps.cc.schoolid
                AND firstday <= (SELECT SYSDATE + $this->term_window FROM DUAL)
                AND lastday >= (SELECT SYSDATE - $this->term_window FROM DUAL)
            )
            ");
        if (!$enrolments) {
            return false;
        }
        foreach ($enrolments as &$enrolment) {
            # In this schema we only get students here.
            $enrolment->isstudent = 1;
            $enrolment->user_idstring = $this->idstring_2local($enrolment->user_idstring, false);
        }
        return $enrolments;
    }

    # Temporary hack for schema-specific config because we're not implmenting an 
    # admin configuration UI in 1.9.
    public function get_temp19_config($host) {
        global $CFG;
        $host->schoolids = $CFG->enrol_mnetdb_schoolids;
        return $host;
    }
}

?>
