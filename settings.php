<?php
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/assign/adminlib.php');
$settings = new admin_settingpage('local_amos', get_string('pluginname', 'local_amos'));
$ADMIN->add('localplugins', $settings);

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('local_amos/applangindexfile', get_string('applangindexfile', 'local_amos'), get_string('applangindexfile_desc', 'local_amos'), 'https://raw.githubusercontent.com/moodlehq/moodlemobile2/integration/scripts/langindex.json', PARAM_TEXT));
}


