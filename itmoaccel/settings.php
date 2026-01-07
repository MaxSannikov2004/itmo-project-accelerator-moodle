<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_itmoaccel', get_string('pluginname', 'local_itmoaccel'));

    $settings->add(new admin_setting_configtext(
        'local_itmoaccel/ai_base_url',
        get_string('ai_base_url', 'local_itmoaccel'),
        get_string('ai_base_url_desc', 'local_itmoaccel'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_itmoaccel/ai_token',
        get_string('ai_token', 'local_itmoaccel'),
        get_string('ai_token_desc', 'local_itmoaccel'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
}