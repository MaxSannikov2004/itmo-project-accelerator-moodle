<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/materials.php'));
$PAGE->set_title(get_string('materials', 'local_itmoaccel'));
$PAGE->set_heading(get_string('materials', 'local_itmoaccel'));

global $DB;

$materials = $DB->get_records('local_itmoaccel_materials', ['enabled' => 1], 'sortorder ASC, id ASC');

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('materials', 'local_itmoaccel'));

if (!$materials) {
    echo html_writer::div('Материалы пока не добавлены.', 'itmo-muted');
    echo $OUTPUT->footer();
    exit;
}

$fs = get_file_storage();
$sysctx = context_system::instance();

echo html_writer::start_tag('ul');
foreach ($materials as $m) {
    $files = $fs->get_area_files($sysctx->id, 'local_itmoaccel', 'material', (int)$m->id, 'id DESC', false);
    $file = $files ? reset($files) : null;

    if ($file) {
        $url = moodle_url::make_pluginfile_url(
            $sysctx->id,
            'local_itmoaccel',
            'material',
            (int)$m->id,
            '/',
            $file->get_filename(),
            true
        );
        echo html_writer::tag('li', html_writer::link($url, s($m->title)));
    } else {
        echo html_writer::tag('li', s($m->title) . ' (файл не найден)');
    }
}
echo html_writer::end_tag('ul');

echo $OUTPUT->footer();
