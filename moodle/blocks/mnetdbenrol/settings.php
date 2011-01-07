<?php
# $Id$

$settings->add(new admin_setting_configtext('block_mnetdbenrol_blockname', get_string('name'), get_string('configblockname_desc', 'block_mnetdbenrol'), get_string('configblockname_default', 'block_mnetdbenrol'), PARAM_TEXT));
$settings->add(new admin_setting_configtext('block_mnetdbenrol_sisname', get_string('sisname', 'block_mnetdbenrol'), get_string('configsisname_desc', 'block_mnetdbenrol'), get_string('configsisname_default', 'block_mnetdbenrol'), PARAM_TEXT));

?>
