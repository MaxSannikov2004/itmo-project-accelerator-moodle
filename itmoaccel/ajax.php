<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_login();

$action = required_param('action', PARAM_ALPHA);
require_sesskey();

$context = context_system::instance();
$projectid = required_param('projectid', PARAM_INT);

if ($action === 'usetopic') {
    $topic = required_param('topic', PARAM_TEXT);

    $project = \local_itmoaccel\service\project_service::get_project($projectid);
    if ((int)$project->userid !== (int)$USER->id) {
        throw new required_capability_exception($context, 'local/itmoaccel:student', 'nopermissions', '');
    }

    $topicdef = \local_itmoaccel\service\stage_service::get_stage_def_by_shortname('topic');
    if (!$topicdef) {
        throw new moodle_exception('Missing stage def: topic');
    }
    \local_itmoaccel\service\stage_service::upsert_text_submission($projectid, (int)$topicdef->id, $USER->id, $topic);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    die();
}

throw new moodle_exception('Unknown action');
