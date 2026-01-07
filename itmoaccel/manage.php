<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/itmoaccel:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/manage.php'));
$PAGE->set_title(get_string('manage', 'local_itmoaccel'));
$PAGE->set_heading(get_string('manage', 'local_itmoaccel'));

global $DB;

$assignform = new \local_itmoaccel\form\assign_form(null);
$importform = new \local_itmoaccel\form\import_form(null);

$notice = '';
$errors = [];

if ($data = $assignform->get_data()) {
    require_sesskey();
    $student = $DB->get_record('user', ['email' => (string)$data->student_email], '*', IGNORE_MISSING);
    $supervisor = $DB->get_record('user', ['email' => (string)$data->supervisor_email], '*', IGNORE_MISSING);

    if (!$student) {
        $errors[] = get_string('err_user_not_found', 'local_itmoaccel', (string)$data->student_email);
    } else if (!$supervisor) {
        $errors[] = get_string('err_user_not_found', 'local_itmoaccel', (string)$data->supervisor_email);
    } else {
        $project = \local_itmoaccel\service\project_service::get_active_project((int)$student->id);
        \local_itmoaccel\service\project_service::assign_supervisor((int)$project->id, (int)$supervisor->id);
        $notice = 'Назначено.';
    }
}

if ($idata = $importform->get_data()) {
    require_sesskey();

    $draftid = $idata->importfile;
    $tempdir = make_temp_directory('itmoaccel');
    $filepath = $tempdir . '/' . uniqid('import_', true);

    // Save uploaded file to temp.
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id DESC', false);
    $file = reset($files);
    if ($file) {
        $filepath .= '_' . $file->get_filename();
        $file->copy_content_to($filepath);

        $res = \local_itmoaccel\service\import_service::import_assignments($filepath);
        $notice = "Импорт: OK={$res['ok']}";
        $errors = array_merge($errors, $res['errors']);
        @unlink($filepath);
    } else {
        $errors[] = 'Файл не найден в draft area.';
    }
}

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('manage', 'local_itmoaccel'));

if ($notice !== '') {
    echo $OUTPUT->notification(s($notice), 'notifysuccess');
}
foreach ($errors as $e) {
    echo $OUTPUT->notification(s($e), 'notifyproblem');
}

echo html_writer::tag('h3', get_string('assign_manual', 'local_itmoaccel'));
$assignform->display();

echo html_writer::tag('h3', get_string('import', 'local_itmoaccel'), ['class' => 'mt-4']);
$importform->display();

echo $OUTPUT->footer();
