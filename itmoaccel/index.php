<?php
require_once(__DIR__ . '/../../config.php');

require_login();

if (\local_itmoaccel\service\access_service::is_staff() ||
    \local_itmoaccel\service\access_service::is_approved_participant((int)$USER->id)) {
    redirect(new moodle_url('/local/itmoaccel/projects.php'));
}

redirect(new moodle_url('/local/itmoaccel/apply.php'));