<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/itmoaccel:supervisor', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/supervisor.php'));
$PAGE->set_title(get_string('nav_supervisor', 'local_itmoaccel'));
$PAGE->set_heading(get_string('nav_supervisor', 'local_itmoaccel'));
$PAGE->requires->css(new moodle_url('/local/itmoaccel/styles.css'));

$filter = optional_param('filter', 'pending', PARAM_ALPHA); // pending|all|rejected|draft|approved

$projects = \local_itmoaccel\service\project_service::list_projects_for_supervisor((int)$USER->id);
$stagedefs = \local_itmoaccel\service\stage_service::get_stage_defs();

global $DB;

function itmoaccel_filter_match_any(string $filter, array $statuses): bool {
    if ($filter === 'all') return true;
    $want = null;
    if ($filter === 'pending')  $want = \local_itmoaccel\service\stage_service::STATUS_PENDING;
    if ($filter === 'rejected') $want = \local_itmoaccel\service\stage_service::STATUS_REJECTED;
    if ($filter === 'draft')    $want = \local_itmoaccel\service\stage_service::STATUS_DRAFT;
    if ($filter === 'approved') $want = \local_itmoaccel\service\stage_service::STATUS_APPROVED;
    if ($want === null) return true;

    foreach ($statuses as $s) {
        if ((int)$s === (int)$want) return true;
    }
    return false;
}

$rows = [];
foreach ($projects as $p) {
    $student = $DB->get_record('user', ['id' => (int)$p->userid], '*', IGNORE_MISSING);
    $profile = $DB->get_record('local_itmoaccel_profiles', ['userid' => (int)$p->userid], '*', IGNORE_MISSING);

    $chips = [];
    $statuses = [];

    foreach ($stagedefs as $sd) {
        $sub = \local_itmoaccel\service\stage_service::get_latest_submission((int)$p->id, (int)$sd->id);
        $status = $sub ? (int)$sub->status : \local_itmoaccel\service\stage_service::STATUS_DRAFT;
        $statuses[] = $status;

        $chipcls = 'itmo-chip';
        if ($status === \local_itmoaccel\service\stage_service::STATUS_PENDING) $chipcls .= ' itmo-chip--pending';
        if ($status === \local_itmoaccel\service\stage_service::STATUS_APPROVED) $chipcls .= ' itmo-chip--ok';
        if ($status === \local_itmoaccel\service\stage_service::STATUS_REJECTED) $chipcls .= ' itmo-chip--bad';

        $url = new moodle_url('/local/itmoaccel/project.php', ['projectid' => (int)$p->id, 'stage' => $sd->shortname]);
        $chips[] = html_writer::link($url, s($sd->title), ['class' => $chipcls, 'title' => \local_itmoaccel\service\stage_service::status_label($status)]);
    }

    if (!itmoaccel_filter_match_any($filter, $statuses)) {
        continue;
    }

    $rows[] = (object)[
        'projectid' => (int)$p->id,
        'projectname' => (string)$p->name,
        'studentname' => $student ? fullname($student) : ('User #' . (int)$p->userid),
        'schoolclass' => trim(((string)($profile->school ?? '')) . ' / ' . ((string)($profile->class ?? '')), " /"),
        'chipshtml' => html_writer::div(implode('', $chips), 'itmo-chiprow'),
        'openurl' => new moodle_url('/local/itmoaccel/project.php', ['projectid' => (int)$p->id]),
    ];
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('nav_supervisor', 'local_itmoaccel'));

// Filters
$base = new moodle_url('/local/itmoaccel/supervisor.php');
$filters = [
    'pending' => 'Есть на согласовании',
    'rejected' => 'Есть отклонённые',
    'draft' => 'Есть в работе',
    'approved' => 'Есть согласованные',
    'all' => 'Все',
];

echo html_writer::start_div('itmo-steps mb-3');
foreach ($filters as $k => $label) {
    $cls = 'itmo-step';
    if ($filter === $k) $cls .= ' is-active';
    echo html_writer::link(new moodle_url($base, ['filter' => $k]), $label, ['class' => $cls]);
}
echo html_writer::end_div();

if (!$rows) {
    echo html_writer::div('Пока нет проектов по выбранному фильтру.', 'itmo-muted');
    echo $OUTPUT->footer();
    exit;
}

foreach ($rows as $r) {
    echo html_writer::start_div('itmo-card itmo-stack mb-3');

    echo html_writer::div(
        html_writer::tag('div', html_writer::tag('strong', s($r->studentname)) . ' — ' . s($r->projectname)) .
        html_writer::tag('div', s($r->schoolclass), ['class' => 'itmo-muted']),
        ''
    );

    echo $r->chipshtml;

    echo html_writer::div(
        html_writer::link($r->openurl, 'Открыть проект', ['class' => 'btn btn-primary btn-sm']),
        ''
    );

    echo html_writer::end_div();
}

echo $OUTPUT->footer();