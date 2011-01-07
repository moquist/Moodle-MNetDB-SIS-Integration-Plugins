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


defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

global $CFG;
require_once(dirname(__FILE__).'/lib.php');

class block_mnetdbenrol extends block_base {

    function init() {
        global $CFG, $USER;
        $this->title = strlen($CFG->block_mnetdbenrol_blockname) ? $CFG->block_mnetdbenrol_blockname : get_string('configblockname_default', 'block_mnetdbenrol');
        $this->version = 2010120301;
        #$this->cron = 1800;
    }

    function get_content() {
        global $CFG, $USER, $COURSE;

        if (!$site = get_site()) { error(__FUNCTION__, "Site isn't defined!"); }

        $this->content = new object;
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            return $this->content;
        }

        $this->content->text .= block_mnetdbenrol_managemaps_context($COURSE);

        return $this->content;
    }

    function cron() {
        global $CFG, $USER, $COURSE, $db;
        return true;
    }

    function has_config() {return true;}
}

?>
