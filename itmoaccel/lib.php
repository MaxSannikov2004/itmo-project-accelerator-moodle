<?php
defined('MOODLE_INTERNAL') || die();

function local_itmoaccel_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $url = new moodle_url('/local/itmoaccel/projects.php');
    $navigation->add(get_string('pluginname', 'local_itmoaccel'), $url, navigation_node::TYPE_CUSTOM);
}

function local_itmoaccel_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        send_file_not_found();
    }
    require_login();

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . implode('/', $args) . '/';

    $fs = get_file_storage();

    // materials: itemid = materialid
    if ($filearea === 'material') {
        $file = $fs->get_file($context->id, 'local_itmoaccel', 'material', $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        send_stored_file($file, 0, 0, true, $options);
    }

    // stagefiles: itemid = submissionid
    if ($filearea === 'stagefiles') {
        $file = $fs->get_file($context->id, 'local_itmoaccel', 'stagefiles', $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        send_stored_file($file, 0, 0, true, $options);
    }

    send_file_not_found();
}
