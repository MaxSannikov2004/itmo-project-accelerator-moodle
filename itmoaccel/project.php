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
$links = [];
$links[] = html_writer::link(new moodle_url('/local/itmoaccel/projects.php'), get_string('nav_projects', 'local_itmoaccel'), ['class' => 'me-3']);
$links[] = html_writer::link(new moodle_url('/local/itmoaccel/profile.php'), get_string('nav_profile', 'local_itmoaccel'), ['class' => 'me-3']);
$links[] = html_writer::link(new moodle_url('/local/itmoaccel/materials.php'), get_string('nav_materials', 'local_itmoaccel'), ['class' => 'me-3']);

if (has_capability('local/itmoaccel:supervisor', $context)) {
    $links[] = html_writer::link(new moodle_url('/local/itmoaccel/supervisor.php'), get_string('nav_supervisor', 'local_itmoaccel'), ['class' => 'me-3']);
}
if (has_capability('local/itmoaccel:manage', $context)) {
    $links[] = html_writer::link(new moodle_url('/local/itmoaccel/manage.php'), get_string('nav_manage', 'local_itmoaccel'), ['class' => 'me-3']);
    $links[] = html_writer::link(new moodle_url('/local/itmoaccel/manage_stages.php'), get_string('nav_stages', 'local_itmoaccel'), ['class' => 'me-3']);
    $links[] = html_writer::link(new moodle_url('/local/itmoaccel/manage_materials.php'), get_string('manage_materials', 'local_itmoaccel'), ['class' => 'me-3']);
}

echo html_writer::div(implode('', $links), 'mb-3');


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

echo html_writer::start_div('itmo-card itmo-stack');

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

// Files stage (upload + submit)
if ($stagedef->handlertype === 'files') {
    // Всегда обеспечиваем сабмишен, чтобы было к чему привязать filearea.
    $filesub = \local_itmoaccel\service\stage_service::ensure_files_submission($projectid, (int)$stagedef->id);

    $sysctx = context_system::instance();
    $options = [
        'subdirs' => 0,
        'maxfiles' => 10,
        'accepted_types' => '*',
        'return_types' => FILE_INTERNAL,
    ];

    // Показ статуса этапа
    $currentstatus = $filesub ? (int)$filesub->status : \local_itmoaccel\service\stage_service::STATUS_DRAFT;
    $badgecls = 'itmo-badge itmo-badge--draft';
    if ($currentstatus === \local_itmoaccel\service\stage_service::STATUS_PENDING) $badgecls = 'itmo-badge itmo-badge--pending';
    if ($currentstatus === \local_itmoaccel\service\stage_service::STATUS_APPROVED) $badgecls = 'itmo-badge itmo-badge--ok';
    if ($currentstatus === \local_itmoaccel\service\stage_service::STATUS_REJECTED) $badgecls = 'itmo-badge itmo-badge--bad';

    echo html_writer::div(
        html_writer::tag('span', \local_itmoaccel\service\stage_service::status_label($currentstatus), ['class' => $badgecls]),
        'mb-3'
    );

    // Если отклонено — комментарий
    if ($filesub && (int)$filesub->status === \local_itmoaccel\service\stage_service::STATUS_REJECTED && !empty($filesub->decisioncomment)) {
        echo $OUTPUT->notification('Комментарий руководителя: ' . s($filesub->decisioncomment), 'notifyproblem');
    }

    // Форма загрузки доступна только владельцу проекта
    if ($isowner) {
        $draftid = file_get_submitted_draft_itemid('files_draft');
        file_prepare_draft_area($draftid, $sysctx->id, 'local_itmoaccel', 'stagefiles', (int)$filesub->id, $options);

        $fform = new \local_itmoaccel\form\stage_files_form(null);
        $fform->set_data([
            'files_draft' => $draftid,
            'submissionid' => (int)$filesub->id,
        ]);

        if ($fform->is_cancelled()) {
            redirect($PAGE->url);
        } else if ($fdata = $fform->get_data()) {
            require_sesskey();

            // Сохраняем файлы из draft area в plugin file area
            file_save_draft_area_files((int)$fdata->files_draft, $sysctx->id, 'local_itmoaccel', 'stagefiles', (int)$filesub->id, $options);

            // Обновим timemodified
            global $DB;
            $filesub->timemodified = time();
            if ((int)$filesub->status === \local_itmoaccel\service\stage_service::STATUS_REJECTED) {
                $filesub->status = \local_itmoaccel\service\stage_service::STATUS_DRAFT;
            }
            $DB->update_record('local_itmoaccel_submissions', $filesub);

            // Если нажали “submit”
            $submit = optional_param('submitbtn', '', PARAM_RAW);
            if ($submit !== '') {
                \local_itmoaccel\service\stage_service::mark_pending((int)$filesub->id);
                if ($supervisor) {
                    \local_itmoaccel\service\notifier::notify_stage_submitted($USER->id, (int)$supervisor->id, $project->name, $stagedef->title);
                }
            }

            redirect($PAGE->url);
        }

        echo html_writer::div('', 'itmo-card itmo-card--soft');
        $fform->display();
    }

    // Просмотр файлов (и школьнику, и руководителю)
    $fs = get_file_storage();
    $files = $fs->get_area_files($sysctx->id, 'local_itmoaccel', 'stagefiles', (int)$filesub->id, 'filename ASC', false);

    echo html_writer::tag('h4', 'Файлы этапа', ['class' => 'mt-4']);

    if (!$files) {
        echo html_writer::div('Файлы ещё не загружены.', 'itmo-muted');
    } else {
        echo html_writer::start_tag('ul', ['class' => 'itmo-filelist']);
        foreach ($files as $f) {
            $url = moodle_url::make_pluginfile_url(
                $sysctx->id,
                'local_itmoaccel',
                'stagefiles',
                (int)$filesub->id,
                '/',
                $f->get_filename(),
                true
            );
            echo html_writer::tag('li', html_writer::link($url, s($f->get_filename())));
        }
        echo html_writer::end_tag('ul');
    }

    // Обновим $latest, чтобы блок согласования ниже работал с files submission
    $latest = $filesub;
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

echo html_writer::end_div();

echo $OUTPUT->footer();