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
global $DB;

// Generate/rotate code
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'newcode') {
    require_sesskey();
    $now = time();
    $code = strtoupper(random_string(8)); // например ABCD1234
    $existing = $DB->get_record('local_itmoaccel_supervisor_codes', ['supervisorid' => $USER->id], '*', IGNORE_MISSING);
    if ($existing) {
        $existing->code = $code;
        $existing->enabled = 1;
        $existing->timemodified = $now;
        $DB->update_record('local_itmoaccel_supervisor_codes', $existing);
    } else {
        $DB->insert_record('local_itmoaccel_supervisor_codes', (object)[
            'supervisorid' => $USER->id,
            'code' => $code,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
    redirect($PAGE->url);
}

// Approve/reject request
if ($action === 'decidereq') {
    require_sesskey();
    $reqid = required_param('reqid', PARAM_INT);
    $decision = required_param('decision', PARAM_ALPHA); // approve|reject
    $comment = optional_param('comment', '', PARAM_TEXT);

    $req = $DB->get_record('local_itmoaccel_requests', ['id' => $reqid], '*', MUST_EXIST);
    if ((int)$req->supervisorid !== (int)$USER->id) {
        throw new required_capability_exception($context, 'local/itmoaccel:supervisor', 'nopermissions', '');
    }
    if ((int)$req->status !== 0) {
        redirect($PAGE->url);
    }

    $now = time();
    $req->status = ($decision === 'approve') ? 10 : 20;
    $req->timedecided = $now;
    $req->deciderid = $USER->id;
    $req->decisioncomment = $comment;
    $DB->update_record('local_itmoaccel_requests', $req);

    if ($decision === 'approve') {
        // Mark participant approved
        $part = $DB->get_record('local_itmoaccel_participants', ['userid' => (int)$req->studentid], '*', IGNORE_MISSING);
        if ($part) {
            $part->status = 10;
            $part->timeapproved = $now;
            $part->timemodified = $now;
            $DB->update_record('local_itmoaccel_participants', $part);
        } else {
            $DB->insert_record('local_itmoaccel_participants', (object)[
                'userid' => (int)$req->studentid,
                'status' => 10,
                'timeapproved' => $now,
                'timemodified' => $now,
            ]);
        }

        // Create/activate project and assign supervisor
        $project = \local_itmoaccel\service\project_service::get_active_project((int)$req->studentid);
        \local_itmoaccel\service\project_service::assign_supervisor((int)$project->id, (int)$USER->id);

        // Notify student
        \local_itmoaccel\service\notifier::notify_stage_decided((int)$USER->id, (int)$req->studentid, 'Заявка в акселератор', 'Доступ открыт', true);
    } else {
        \local_itmoaccel\service\notifier::notify_stage_decided((int)$USER->id, (int)$req->studentid, 'Заявка в акселератор', 'Заявка отклонена', false);
    }

    redirect($PAGE->url);
}


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

// My code
$coderec = $DB->get_record('local_itmoaccel_supervisor_codes', ['supervisorid' => $USER->id], '*', IGNORE_MISSING);
$code = $coderec ? $coderec->code : '—';

echo html_writer::start_div('itmo-card itmo-stack mb-3');
echo html_writer::tag('div', html_writer::tag('strong', 'Мой код руководителя: ') . html_writer::tag('span', s($code), ['style' => 'font-family:monospace;font-size:18px']));
echo html_writer::link(new moodle_url($PAGE->url, ['action' => 'newcode', 'sesskey' => sesskey()]), 'Сгенерировать новый код', ['class' => 'btn btn-outline-secondary btn-sm']);
echo html_writer::end_div();

// Requests
$requests = $DB->get_records('local_itmoaccel_requests', ['supervisorid' => $USER->id, 'status' => 0], 'timecreated DESC');

echo html_writer::tag('h3', 'Заявки');
if (!$requests) {
    echo html_writer::div('Нет заявок.', 'itmo-muted mb-3');
} else {
    foreach ($requests as $r) {
        $stu = $DB->get_record('user', ['id' => (int)$r->studentid], '*', IGNORE_MISSING);
        $prof = $DB->get_record('local_itmoaccel_profiles', ['userid' => (int)$r->studentid], '*', IGNORE_MISSING);

        echo html_writer::start_div('itmo-card itmo-stack mb-3');
        echo html_writer::tag('div', html_writer::tag('strong', $stu ? fullname($stu) : ('User #' . (int)$r->studentid)));
        echo html_writer::tag('div', s(trim(((string)($prof->school ?? '')) . ' / ' . ((string)($prof->class ?? '')), " /")), ['class' => 'itmo-muted']);
        if (!empty($r->message)) {
            echo html_writer::tag('div', 'Сообщение: ' . s($r->message));
        }

        // Approve form
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'itmo-stack']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'decidereq']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reqid', 'value' => (int)$r->id]);

        echo html_writer::tag('textarea', '', ['name' => 'comment', 'rows' => 2, 'placeholder' => 'Комментарий (необязательно)', 'style' => 'width:100%']);

        echo html_writer::start_div();
        echo html_writer::empty_tag('button', ['type' => 'submit', 'name' => 'decision', 'value' => 'approve', 'class' => 'btn btn-success btn-sm me-2', 'content' => 'Одобрить']);
        // Moodle не любит button с content через empty_tag; проще:
        echo '<button type="submit" name="decision" value="approve" class="btn btn-success btn-sm me-2">Одобрить</button>';
        echo '<button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm">Отклонить</button>';
        echo html_writer::end_div();

        echo html_writer::end_tag('form');

        echo html_writer::end_div();
    }
}


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