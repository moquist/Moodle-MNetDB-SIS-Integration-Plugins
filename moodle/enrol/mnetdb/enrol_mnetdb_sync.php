<?php // $Id: enrol_database_sync.php,v 1.6.2.1 2008/01/02 22:35:33 skodak Exp $

    if (PHP_SAPI != 'cli') {
        error_log("Must be called from CLI!");
        exit;
    }

    error_reporting(E_ALL);
    
    require_once(dirname(dirname(dirname(__FILE__))).'/config.php'); // global moodle config file.

    require_once($CFG->dirroot . "/enrol/mnetdb/enrol.php");

    // ensure errors are well explained
    $CFG->debug=E_ALL;

    if (!is_enabled_enrol('mnetdb')) {
         error_log("MNetDB enrolment plugin not enabled!");
         die;
    }

    $enrol = new enrolment_plugin_mnetdb();

    $enrol->sync_enrolments();
?>
