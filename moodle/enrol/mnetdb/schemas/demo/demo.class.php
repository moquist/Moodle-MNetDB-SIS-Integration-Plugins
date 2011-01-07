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

class enrol_mnetdb_schema_demo extends enrol_mnetdb_schema_base {

    function get_users($host, $username='') {
        global $CFG;
        if (strlen($username)) {
            $username = " AND username = '$username' ";
        }
        return enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'username', 'hostid', 'isteacher', 'isstudent'),
            "SELECT id, username, $host->id, isteacher, isstudent FROM usr WHERE 1=1 $username"
        );
    }

    function get_courses($host) {
        global $CFG;
        return enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'name', 'hostid'),
            "SELECT id, name, $host->id FROM course"
        );
    }

    function get_coursesections($host) {
        global $CFG;
        $sql = "
            SELECT classroom.id, course.id, course.name, $host->id
            FROM classroom
            JOIN course ON course.id = classroom.courseid
            WHERE classroom.termid IN (
                SELECT id FROM term
                WHERE startdate <= (SELECT NOW() + INTERVAL '$this->term_window DAYS')
                AND enddate >= (SELECT NOW() - INTERVAL '$this->term_window DAYS')
            )
            ";
        # Get all the 'sections' in the current term.
        $tmp = enrol_mnetdb_sql_asobj($host->id, array('idstring', 'course_idstring', 'coursename', 'hostid'), $sql);
        $sections = array();
        foreach ($tmp as $section) {
            # Put the displayname together during the initial data query -- this
            # seems simpler (for now) than providing a plugin-specific interface to
            # fetch an SIS-specific display name later.
            $section->displayname = "$section->coursename";
            $sections[] = $section;
        }
        return $sections;
    }

    function get_enrolments($host) {
        global $CFG;
        return enrol_mnetdb_sql_asobj($host->id,
            array('idstring', 'user_idstring', 'coursesection_idstring', 'isteacher', 'isstudent', 'hostid'), "
            SELECT enrolment.id, enrolment.usrid, enrolment.classroomid, isteacher, isstudent, $host->id
            FROM enrolment
            JOIN classroom ON classroom.id = enrolment.classroomid
            WHERE classroom.termid IN (
                SELECT id FROM term
                WHERE startdate <= (SELECT NOW() + INTERVAL '$this->term_window DAYS')
                AND enddate >= (SELECT NOW() - INTERVAL '$this->term_window DAYS')
            )
            ");
    }

}

?>
