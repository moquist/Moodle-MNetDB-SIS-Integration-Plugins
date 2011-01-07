<?php
$courseid = optional_param('courseid', null, PARAM_INT);
$site = get_site();
$strheading = strlen($CFG->block_mnetdbenrol_blockname) ? $CFG->block_mnetdbenrol_blockname : get_string('configblockname_default', 'block_mnetdbenrol');
$nav = array();
if ($courseid) {
    $course = get_record('course', 'id', $courseid);
    $url = "$CFG->wwwroot/course/view.php?id=$courseid";
    $nav[] = array( 'name' => $course->shortname, 'link' => $url);
}
$nav[] = array( 'name' => $strheading, 'link' => '');
print_header("$site->shortname: $strheading", $site->fullname, build_navigation($nav));


?>
