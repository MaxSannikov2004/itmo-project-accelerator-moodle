<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/profile.php'));
$PAGE->set_title(get_string('profile', 'local_itmoaccel'));
$PAGE->set_heading(get_string('profile', 'local_itmoaccel'));

global $DB;

$rec = $DB->get_record('local_itmoaccel_profiles', ['userid' => $USER->id], '*', IGNORE_MISSING);
$form = new \local_itmoaccel\form\profile_form(null);

if ($rec) {
    $form->set_data(['school' => $rec->school, 'class' => $rec->class]);
}

if ($data = $form->get_data()) {
    require_sesskey();
    $now = time();
    if (!$rec) {
        $DB->insert_record('local_itmoaccel_profiles', (object)[
            'userid' => $USER->id,
            'school' => (string)$data->school,
            'class' => (string)$data->class,
            'timemodified' => $now,
        ]);
    } else {
        $rec->school = (string)$data->school;
        $rec->class = (string)$data->class;
        $rec->timemodified = $now;
        $DB->update_record('local_itmoaccel_profiles', $rec);
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('profile', 'local_itmoaccel'));
$form->display();
echo $OUTPUT->footer();
