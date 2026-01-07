<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$projectid = optional_param('projectid', 0, PARAM_INT);
if ($projectid <= 0) {
    $project = \local_itmoaccel\service\project_service::get_active_project($USER->id);
    $projectid = (int)$project->id;
} else {
    $project = \local_itmoaccel\service\project_service::get_project($projectid);
    if ((int)$project->userid !== (int)$USER->id && !\local_itmoaccel\service\project_service::is_supervisor_for_project($USER->id, $projectid)) {
        throw new required_capability_exception($context, 'local/itmoaccel:student', 'nopermissions', '');
    }
}

$stages = \local_itmoaccel\service\stage_service::get_stage_defs();
$stagesbyshort = [];
foreach ($stages as $sd) { $stagesbyshort[$sd->shortname] = $sd; }

$stage = optional_param('stage', '', PARAM_ALPHANUMEXT);
if ($stage === '' || !isset($stagesbyshort[$stage])) {
    $stage = array_key_first($stagesbyshort);
}
$stagedef = $stagesbyshort[$stage];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/project.php', ['projectid' => $projectid, 'stage' => $stage]));
$PAGE->set_title(get_string('nav_project', 'local_itmoaccel'));
$PAGE->set_heading(s($project->name));
$PAGE->requires->css(new moodle_url('/local/itmoaccel/styles.css'));

$isowner = ((int)$project->userid === (int)$USER->id);
$issupervisor = \local_itmoaccel\service\project_service::is_supervisor_for_project($USER->id, $projectid);

$latest = \local_itmoaccel\service\stage_service::get_latest_submission($projectid, (int)$stagedef->id);
$data = $latest && $latest->datajson ? json_decode($latest->datajson, true) : [];
$textvalue = is_array($data) ? (string)($data['text'] ?? '') : '';

echo $OUTPUT->header();

// Nav
echo html_writer::div(
    html_writer::link(new moodle_url('/local/itmoaccel/projects.php'), get_string('nav_projects', 'local_itmoaccel'), ['class' => 'me-3']) .
    html_writer::link(new moodle_url('/local/itmoaccel/profile.php'), get_string('nav_profile', 'local_itmoaccel'), ['class' => 'me-3']) .
    html_writer::link(new moodle_url('/local/itmoaccel/materials.php'), get_string('nav_materials', 'local_itmoaccel')),
    'mb-3'
);

// Progress bar
echo html_writer::start_tag('div', ['class' => 'itmo-steps']);
foreach ($stages as $sd) {
    $sub = \local_itmoaccel\service\stage_service::get_latest_submission($projectid, (int)$sd->id);
    $status = $sub ? (int)$sub->status : \local_itmoaccel\service\stage_service::STATUS_DRAFT;
    $cls = 'itmo-step';
    if ($sd->shortname === $stage) { $cls .= ' is-active'; }
    if ($status === \local_itmoaccel\service\stage_service::STATUS_APPROVED) { $cls .= ' is-ok'; }
    if ($status === \local_itmoaccel\service\stage_service::STATUS_PENDING) { $cls .= ' is-pending'; }
    if ($status === \local_itmoaccel\service\stage_service::STATUS_REJECTED) { $cls .= ' is-bad'; }

    $u = new moodle_url('/local/itmoaccel/project.php', ['projectid' => $projectid, 'stage' => $sd->shortname]);
    echo html_writer::link($u, s($sd->title), ['class' => $cls]);
}
echo html_writer::end_tag('div');

echo html_writer::tag('h3', s($stagedef->title), ['class' => 'mt-3']);

// Supervisor info
$supervisor = \local_itmoaccel\service\project_service::get_supervisor_user($projectid);
if ($supervisor) {
    echo html_writer::div('Руководитель: ' . fullname($supervisor), 'itmo-muted mb-3');
} else {
    echo html_writer::div('Руководитель: не назначен', 'itmo-muted mb-3');
}

// Handle AI topics generation (simple)
$topics = [];
$aierror = '';
if ($isowner && $stagedef->handlertype === 'ai_topics') {
    $aiform = new \local_itmoaccel\form\ai_topics_form(null, null, 'post', '', ['class' => 'mb-3']);
    if ($aiform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $aiform->get_data()) {
        require_sesskey();
        try {
            $topics = \local_itmoaccel\service\ai_client::generate_batch_topics((string)$data->prompt, (int)$data->count);
            // Save topics as submission text (store under "text" for simplicity).
            $saved = \local_itmoaccel\service\stage_service::upsert_text_submission($projectid, (int)$stagedef->id, $USER->id, implode("\n", $topics));
            $latest = $saved;
        } catch (Throwable $e) {
            $aierror = $e->getMessage();
        }
    }
    if ($aierror !== '') {
        echo $OUTPUT->notification(s($aierror), 'notifyproblem');
    }
    $aiform->display();
}

// Text stage form (topic/goals/plan/text)
if ($stagedef->handlertype === 'text' && $isowner) {
    $mform = new \local_itmoaccel\form\stage_text_form(null);
    $toform = ['text' => $textvalue, 'action' => 'textsave'];
    $mform->set_data($toform);

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($formdata = $mform->get_data()) {
        require_sesskey();
        $saved = \local_itmoaccel\service\stage_service::upsert_text_submission($projectid, (int)$stagedef->id, $USER->id, (string)$formdata->text);

        // Determine which button pressed:
        $submit = optional_param('submitbtn', '', PARAM_RAW);
        if ($submit !== '') {
            \local_itmoaccel\service\stage_service::mark_pending((int)$saved->id);
            // notify supervisor
            if ($supervisor) {
                \local_itmoaccel\service\notifier::notify_stage_submitted($USER->id, (int)$supervisor->id, $project->name, $stagedef->title);
            }
        }
        redirect($PAGE->url);
    }

    // If rejected, show comment.
    if ($latest && (int)$latest->status === \local_itmoaccel\service\stage_service::STATUS_REJECTED && !empty($latest->decisioncomment)) {
        echo $OUTPUT->notification('Комментарий руководителя: ' . s($latest->decisioncomment), 'notifyproblem');
    }

    $mform->display();
}

// Display AI topics as list with "use topic" (writes into stage 'topic')
if ($stagedef->handlertype === 'ai_topics') {
    $sub = \local_itmoaccel\service\stage_service::get_latest_submission($projectid, (int)$stagedef->id);
    $t = '';
    if ($sub && $sub->datajson) {
        $d = json_decode($sub->datajson, true);
        $t = is_array($d) ? (string)($d['text'] ?? '') : '';
    }
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $t ?? ''))));
    if ($lines) {
        echo html_writer::tag('h4', 'Варианты тем', ['class' => 'mt-4']);
        echo html_writer::start_tag('ul', ['class' => 'itmo-topics']);
        foreach ($lines as $line) {
            $use = '';
            if ($isowner) {
                $ajax = new moodle_url('/local/itmoaccel/ajax.php', [
                    'action' => 'usetopic',
                    'projectid' => $projectid,
                    'topic' => $line,
                    'sesskey' => sesskey()
                ]);
                $use = html_writer::link($ajax, get_string('use_this_topic', 'local_itmoaccel'), ['class' => 'btn btn-outline-primary btn-sm ms-2']);
            }
            echo html_writer::tag('li', s($line) . $use);
        }
        echo html_writer::end_tag('ul');
        echo html_writer::div('Кнопка “Использовать” заполнит этап “Тема” черновиком.', 'itmo-muted');
    } else {
        echo html_writer::div('Пока нет сгенерированных тем.', 'itmo-muted');
    }
}

// Supervisor decision UI
if ($issupervisor && $latest && (int)$latest->status === \local_itmoaccel\service\stage_service::STATUS_PENDING) {
    echo html_writer::tag('h4', 'Согласование', ['class' => 'mt-4']);
    $decision = optional_param('decision', '', PARAM_ALPHA);
    if ($decision !== '') {
        require_sesskey();
        $comment = required_param('comment', PARAM_TEXT);
        $approved = ($decision === 'approve');
        \local_itmoaccel\service\stage_service::decide((int)$latest->id, $USER->id, $approved, $comment);

        // Notify student.
        $studentid = (int)$project->userid;
        \local_itmoaccel\service\notifier::notify_stage_decided($USER->id, $studentid, $project->name, $stagedef->title, $approved);
        redirect($PAGE->url);
    }

    echo html_writer::start_tag('form', ['method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('comment', 'local_itmoaccel'));
    echo html_writer::tag('textarea', '', ['name' => 'comment', 'rows' => 3, 'style' => 'width:100%']);
    echo html_writer::div(
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => 'approve']) .
        html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('approve', 'local_itmoaccel'), 'class' => 'btn btn-success me-2']),
        'mt-2 d-inline-block',
        ['style' => 'display:inline-block']
    );
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mt-2']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('comment', 'local_itmoaccel'));
    echo html_writer::tag('textarea', '', ['name' => 'comment', 'rows' => 3, 'style' => 'width:100%']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => 'reject']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('reject', 'local_itmoaccel'), 'class' => 'btn btn-danger mt-2']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
