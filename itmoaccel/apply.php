<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/apply.php'));
$PAGE->set_title('Заявка в акселератор');
$PAGE->set_heading('Заявка в акселератор');
$PAGE->requires->css(new moodle_url('/local/itmoaccel/styles.css'));

global $DB;

// Если уже approved — сразу в проекты
if (\local_itmoaccel\service\access_service::is_approved_participant((int)$USER->id)) {
    redirect(new moodle_url('/local/itmoaccel/projects.php'));
}

// Статусы заявок:
const ITMOACCEL_REQ_PENDING = 0;
const ITMOACCEL_REQ_APPROVED = 10;
const ITMOACCEL_REQ_REJECTED = 20;
const ITMOACCEL_REQ_CANCELLED = 30;

// Антиспам: не чаще 1 заявки в 5 минут
const ITMOACCEL_COOLDOWN_SECONDS = 300;

// Список руководителей (только с capability supervisor)
$supervisorusers = get_users_by_capability(
    $context,
    'local/itmoaccel:supervisor',
    'u.id,u.firstname,u.lastname,u.email',
    'u.lastname ASC',
    '',
    '',
    '',
    true
);
$options = [0 => '— выбрать —'];
foreach ($supervisorusers as $u) {
    $options[(int)$u->id] = fullname($u) . ' (' . $u->email . ')';
}

$form = new \local_itmoaccel\form\apply_form(null, ['supervisors' => $options]);

$notice = '';
$error = '';

// Текущая pending-заявка (если есть)
$pending = $DB->get_record('local_itmoaccel_requests', ['studentid' => $USER->id, 'status' => ITMOACCEL_REQ_PENDING], '*', IGNORE_MISSING);

// Последняя заявка (для истории/антиспама)
$last = $DB->get_record_sql(
    "SELECT *
       FROM {local_itmoaccel_requests}
      WHERE studentid = :sid
   ORDER BY timecreated DESC",
    ['sid' => $USER->id],
    IGNORE_MULTIPLE
);

// --- Actions: cancel pending ---
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'cancel') {
    require_sesskey();
    if (!$pending) {
        redirect($PAGE->url);
    }

    $pending->status = ITMOACCEL_REQ_CANCELLED;
    $pending->timedecided = time();
    $pending->deciderid = (int)$USER->id;
    $pending->decisioncomment = 'Отменено школьником';
    $DB->update_record('local_itmoaccel_requests', $pending);

    redirect($PAGE->url, 'Заявка отменена. Теперь можно отправить новую.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Если pending есть — форму не обрабатываем, показываем заглушку
if (!$pending) {
    // Подать новую заявку (через форму)
    if ($data = $form->get_data()) {
        require_sesskey();

        // антиспам
        if ($last && (time() - (int)$last->timecreated) < ITMOACCEL_COOLDOWN_SECONDS) {
            $wait = ITMOACCEL_COOLDOWN_SECONDS - (time() - (int)$last->timecreated);
            $error = "Слишком часто. Попробуйте снова через {$wait} сек.";
        } else {
            // Upsert profile (school/class)
            $rec = $DB->get_record('local_itmoaccel_profiles', ['userid' => $USER->id], '*', IGNORE_MISSING);
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

            // Определяем руководителя: приоритет — code, иначе dropdown
            $supervisorid = 0;

            $code = strtoupper(trim((string)$data->code));
            if ($code !== '') {
                $codeRec = $DB->get_record('local_itmoaccel_supervisor_codes', ['code' => $code, 'enabled' => 1], '*', IGNORE_MISSING);
                if ($codeRec) {
                    $supervisorid = (int)$codeRec->supervisorid;
                } else {
                    $error = 'Код руководителя не найден.';
                }
            } else {
                $supervisorid = (int)$data->supervisorid;
                if ($supervisorid <= 0) {
                    $error = 'Выберите руководителя или введите код.';
                }
            }

            // Пишем заявку
            if ($error === '' && $supervisorid > 0) {
                $DB->insert_record('local_itmoaccel_requests', (object)[
                    'studentid' => (int)$USER->id,
                    'supervisorid' => (int)$supervisorid,
                    'status' => ITMOACCEL_REQ_PENDING,
                    'message' => (string)$data->message,
                    'timecreated' => time(),
                ]);

                // Уведомление руководителю (быстрый MVP — потом можно выделить отдельный message provider)
                \local_itmoaccel\service\notifier::notify_stage_submitted(
                    (int)$USER->id,
                    (int)$supervisorid,
                    'Заявка в акселератор',
                    'Новая заявка от ' . fullname($USER)
                );

                redirect($PAGE->url, 'Заявка отправлена. Ожидайте решения руководителя.', null, \core\output\notification::NOTIFY_SUCCESS);
            }
        }
    }
}

echo $OUTPUT->header();
echo html_writer::tag('h2', 'Заявка в акселератор');

if ($notice) echo $OUTPUT->notification(s($notice), 'notifysuccess');
if ($error)  echo $OUTPUT->notification(s($error), 'notifyproblem');

// --- UI: Pending stub ---
if ($pending) {
    $sup = $DB->get_record('user', ['id' => (int)$pending->supervisorid], '*', IGNORE_MISSING);
    $supname = $sup ? fullname($sup) . ' (' . $sup->email . ')' : ('User #' . (int)$pending->supervisorid);

    echo html_writer::start_div('itmo-card itmo-stack mb-3');
    echo html_writer::tag('div', html_writer::tag('strong', 'Статус: ') . html_writer::tag('span', 'Ожидает решения руководителя', ['class' => 'itmo-badge itmo-badge--pending']));
    echo html_writer::tag('div', html_writer::tag('strong', 'Кому отправлено: ') . s($supname));
    echo html_writer::tag('div', html_writer::tag('strong', 'Дата отправки: ') . userdate((int)$pending->timecreated));

    if (!empty($pending->message)) {
        echo html_writer::tag('div', html_writer::tag('strong', 'Ваше сообщение: ') . s($pending->message));
    } else {
        echo html_writer::tag('div', 'Сообщение не указано.', ['class' => 'itmo-muted']);
    }

    // Cancel button
    $cancelurl = new moodle_url($PAGE->url, ['action' => 'cancel', 'sesskey' => sesskey()]);
    echo html_writer::tag('div', html_writer::link($cancelurl, 'Отменить заявку', ['class' => 'btn btn-outline-danger btn-sm']));
    echo html_writer::end_div();

    echo html_writer::div('После отмены вы сможете отправить новую заявку (например, другому руководителю).', 'itmo-muted');

    echo $OUTPUT->footer();
    exit;
}

// --- UI: History (last rejected/cancelled) ---
if ($last && (int)$last->status !== ITMOACCEL_REQ_PENDING) {
    $sup = $DB->get_record('user', ['id' => (int)$last->supervisorid], '*', IGNORE_MISSING);
    $supname = $sup ? fullname($sup) . ' (' . $sup->email . ')' : ('User #' . (int)$last->supervisorid);

    $statuslabel = '—';
    $badge = 'itmo-badge';
    if ((int)$last->status === ITMOACCEL_REQ_REJECTED) { $statuslabel = 'Отклонено'; $badge .= ' itmo-badge--bad'; }
    if ((int)$last->status === ITMOACCEL_REQ_CANCELLED) { $statuslabel = 'Отменено'; $badge .= ' itmo-badge--draft'; }
    if ((int)$last->status === ITMOACCEL_REQ_APPROVED) { $statuslabel = 'Одобрено'; $badge .= ' itmo-badge--ok'; }

    echo html_writer::start_div('itmo-card itmo-stack mb-3');
    echo html_writer::tag('div', html_writer::tag('strong', 'Последняя заявка: ') . html_writer::tag('span', $statuslabel, ['class' => $badge]));
    echo html_writer::tag('div', html_writer::tag('strong', 'Руководитель: ') . s($supname));
    echo html_writer::tag('div', html_writer::tag('strong', 'Дата отправки: ') . userdate((int)$last->timecreated));
    if (!empty($last->decisioncomment)) {
        echo html_writer::tag('div', html_writer::tag('strong', 'Комментарий: ') . s($last->decisioncomment));
    }
    echo html_writer::end_div();
}

// --- Show form (new request) ---
echo html_writer::div('Выберите руководителя из списка или введите код. После одобрения откроются проекты и этапы.', 'itmo-muted mb-3');
$form->display();

echo $OUTPUT->footer();