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

abstract class enrol_mnetdb_schema_base {
    /** @var maybe fudge the start/end of terms slightly for teacher/student convenience */
    protected $term_window = 0;

    protected abstract function get_users($host, $username='');
    protected function cache_users($users) {
        begin_sql();
        # Locally, we key by username because an integration like this isn't even
        # imagineable without that, whereas we can't always assume we have a sane,
        # locally-usable idstring (that would match mdl_user.idnumber, for example).
        foreach ($users as $user) {
            if (!strlen($user->username)) {
                continue;
            }
            $user->username = strtolower($user->username);
            $user = addslashes_object($user);
            if (!$t = get_record('enrol_mnetdb_user', 'username', $user->username, 'hostid', $user->hostid)) {
                insert_record('enrol_mnetdb_user', $user);
            } else {
                $user->id = $t->id;
                if (empty($user->isstudent)) {
                    unset($user->isstudent);
                }
                if (empty($user->isteacher)) {
                    unset($user->isteacher);
                }
                update_record('enrol_mnetdb_user', $user);
            }
        }
        commit_sql();
    }

    protected abstract function get_courses($host);
    protected function cache_courses($courses) {
        begin_sql();
        foreach ($courses as $course) {
            if (!strlen($course->name)) {
                continue;
            }
            $course = addslashes_object($course);
            if (!$t = get_record('enrol_mnetdb_course', 'idstring', $course->idstring, 'hostid', $course->hostid)) {
                insert_record('enrol_mnetdb_course', $course);
            } else {
                $course->id = $t->id;
                update_record('enrol_mnetdb_course', $course);
            }
        }
        commit_sql();
    }

    protected abstract function get_coursesections($host);
    protected function cache_coursesections($coursesections) {
        begin_sql();
        foreach ($coursesections as $coursesection) {
            $coursesection = addslashes_object($coursesection);
            if (!$t = get_record('enrol_mnetdb_coursesection', 'idstring', $coursesection->idstring, 'hostid', $coursesection->hostid)) {
                insert_record('enrol_mnetdb_coursesection', $coursesection);
            } else {
                $coursesection->id = $t->id;
                update_record('enrol_mnetdb_coursesection', $coursesection);
            }
        }
        commit_sql();
    }


    protected abstract function get_enrolments($host);
    protected function cache_enrolments($enrolments) {
        begin_sql();
        foreach ($enrolments as $enrolment) {
            $enrolment = addslashes_object($enrolment);
            if (!$t = get_record('enrol_mnetdb_cs_user', 'coursesection_idstring', $enrolment->coursesection_idstring, 'user_idstring', $enrolment->user_idstring, 'hostid', $enrolment->hostid)) {
                insert_record('enrol_mnetdb_cs_user', $enrolment);
            } else {
                $enrolment->id = $t->id;
                if (empty($enrolment->isstudent)) {
                    unset($enrolment->isstudent);
                }
                if (empty($enrolment->isteacher)) {
                    unset($enrolment->isteacher);
                }
                update_record('enrol_mnetdb_cs_user', $enrolment);
            }
        }
        commit_sql();
    }

    public function sync_enrolments($host) {
        ini_set('memory_limit', -1);
        set_time_limit(0);
        if (!$users = $this->get_users($host)) {
            return;
        }
        $this->cache_users($users);

        if (!$courses = $this->get_courses($host)) {
            return;
        }
        $this->cache_courses($courses);

        if (!$coursesections = $this->get_coursesections($host)) {
            return;
        }
        $this->cache_coursesections($coursesections);

        if (!$enrolments = $this->get_enrolments($host)) {
            return;
        }
        $this->cache_enrolments($enrolments);
    }
    /*
     * Factory
     *
     * @param string $name name of the interpreter that can query the remote DB
     */
    public static final function get_interpreter($name) {
        $class = 'enrol_mnetdb_schema_' . $name;
        return new $class();
    }
}



?>
