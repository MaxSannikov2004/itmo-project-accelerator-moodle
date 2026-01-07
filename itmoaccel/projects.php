<?php
require_once(__DIR__ . '/../../config.php');

require_login();
\local_itmoaccel\service\access_service::require_participant_or_staff();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/projects.php'));
$PAGE->set_title(get_string('projects', 'local_itmoaccel'));
$PAGE->set_heading(get_string('projects', 'local_itmoaccel'));
$PAGE->requires->css(new moodle_url('/local/itmoaccel/styles.css'));

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'create') {
    require_sesskey();
    \local_itmoaccel\service\project_service::create_project($USER->id, 'Проект ' . userdate(time(), '%d.%m.%Y %H:%M'));
    redirect($PAGE->url);
}
if ($action === 'archive') {
    require_sesskey();
    $pid = required_param('projectid', PARAM_INT);
    \local_itmoaccel\service\project_service::archive_project($USER->id, $pid);
    redirect($PAGE->url);
}
if ($action === 'setactive') {
    require_sesskey();
    $pid = required_param('projectid', PARAM_INT);
    \local_itmoaccel\service\project_service::set_active_project($USER->id, $pid);
    redirect(new moodle_url('/local/itmoaccel/project.php', ['projectid' => $pid]));
}

$projects = \local_itmoaccel\service\project_service::list_projects($USER->id);
$active = \local_itmoaccel\service\project_service::get_active_project($USER->id);

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('projects', 'local_itmoaccel'));

$createurl = new moodle_url($PAGE->url, ['action' => 'create', 'sesskey' => sesskey()]);
echo html_writer::link($createurl, get_string('create_project', 'local_itmoaccel'), ['class' => 'btn btn-primary mb-3']);

echo html_writer::start_tag('div', ['class' => 'itmo-list']);
foreach ($projects as $p) {
    $isactive = ((int)$p->id === (int)$active->id);
    $label = $isactive ? ' (текущий)' : '';
    $row = html_writer::tag('strong', s($p->name) . $label);

    $open = html_writer::link(new moodle_url('/local/itmoaccel/project.php', ['projectid' => $p->id]), 'Открыть', ['class' => 'btn btn-secondary btn-sm ms-2']);
    $setactive = '';
    if (!$isactive && (int)$p->isarchived === 0) {
        $setactive = html_writer::link(new moodle_url($PAGE->url, ['action' => 'setactive', 'projectid' => $p->id, 'sesskey' => sesskey()]),
            get_string('set_active', 'local_itmoaccel'), ['class' => 'btn btn-outline-secondary btn-sm ms-2']);
    }
    $archive = '';
    if ((int)$p->isarchived === 0) {
        $archive = html_writer::link(new moodle_url($PAGE->url, ['action' => 'archive', 'projectid' => $p->id, 'sesskey' => sesskey()]),
            get_string('archive_project', 'local_itmoaccel'), ['class' => 'btn btn-outline-danger btn-sm ms-2']);
    } else {
        $archive = html_writer::tag('span', 'Архив', ['class' => 'badge bg-secondary ms-2']);
    }

    echo html_writer::div($row . $open . $setactive . $archive, 'itmo-row');
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
