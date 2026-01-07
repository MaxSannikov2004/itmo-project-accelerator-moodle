<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/itmoaccel:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/manage_stages.php'));
$PAGE->set_title(get_string('nav_stages', 'local_itmoaccel'));
$PAGE->set_heading(get_string('nav_stages', 'local_itmoaccel'));
$PAGE->requires->css(new moodle_url('/local/itmoaccel/styles.css'));

global $DB;

// гарантируем дефолтные этапы, если БД пустая
\local_itmoaccel\service\stage_service::ensure_default_stage_defs();

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action !== '') {
    require_sesskey();

    if ($action === 'toggle' && $id) {
        $rec = $DB->get_record('local_itmoaccel_stage_defs', ['id' => $id], '*', MUST_EXIST);
        $rec->enabled = ((int)$rec->enabled === 1) ? 0 : 1;
        $rec->timemodified = time();
        $DB->update_record('local_itmoaccel_stage_defs', $rec);
        redirect($PAGE->url);
    }

    if (($action === 'up' || $action === 'down') && $id) {
        $rec = $DB->get_record('local_itmoaccel_stage_defs', ['id' => $id], '*', MUST_EXIST);
        $all = $DB->get_records('local_itmoaccel_stage_defs', null, 'sortorder ASC');
        $ids = array_values(array_map(fn($x) => (int)$x->id, $all));
        $pos = array_search((int)$rec->id, $ids, true);

        if ($pos !== false) {
            $swappos = ($action === 'up') ? $pos - 1 : $pos + 1;
            if ($swappos >= 0 && $swappos < count($ids)) {
                $other = $DB->get_record('local_itmoaccel_stage_defs', ['id' => $ids[$swappos]], '*', MUST_EXIST);

                // swap sortorder
                $tmp = (int)$rec->sortorder;
                $rec->sortorder = (int)$other->sortorder;
                $other->sortorder = $tmp;
                $rec->timemodified = time();
                $other->timemodified = time();
                $DB->update_record('local_itmoaccel_stage_defs', $rec);
                $DB->update_record('local_itmoaccel_stage_defs', $other);
            }
        }
        redirect($PAGE->url);
    }
}

// Add stage form
$form = new \local_itmoaccel\form\stage_def_form(null);
$notice = '';
$error = '';

if ($data = $form->get_data()) {
    require_sesskey();

    $short = trim((string)$data->shortname);
    $title = trim((string)$data->title);
    $type  = trim((string)$data->handlertype);

    if ($DB->record_exists('local_itmoaccel_stage_defs', ['shortname' => $short])) {
        $error = "Этап с кодом '{$short}' уже существует.";
    } else {
        $max = $DB->get_field_sql("SELECT COALESCE(MAX(sortorder), 0) FROM {local_itmoaccel_stage_defs}");
        $now = time();
        $DB->insert_record('local_itmoaccel_stage_defs', (object)[
            'shortname' => $short,
            'title' => $title,
            'handlertype' => $type,
            'sortorder' => ((int)$max) + 10,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $notice = 'Этап добавлен.';
    }
}

$defs = $DB->get_records('local_itmoaccel_stage_defs', null, 'sortorder ASC');

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('nav_stages', 'local_itmoaccel'));

if ($notice) echo $OUTPUT->notification(s($notice), 'notifysuccess');
if ($error)  echo $OUTPUT->notification(s($error), 'notifyproblem');

echo html_writer::tag('h3', get_string('add_stage', 'local_itmoaccel'));
$form->display();

echo html_writer::tag('h3', get_string('stages', 'local_itmoaccel'), ['class' => 'mt-4']);

$table = new html_table();
$table->head = ['#', get_string('shortname', 'local_itmoaccel'), get_string('title', 'local_itmoaccel'),
    get_string('handlertype', 'local_itmoaccel'), get_string('enabled', 'local_itmoaccel'), ''];
$table->data = [];

foreach ($defs as $d) {
    $toggle = new moodle_url($PAGE->url, ['action' => 'toggle', 'id' => $d->id, 'sesskey' => sesskey()]);
    $up = new moodle_url($PAGE->url, ['action' => 'up', 'id' => $d->id, 'sesskey' => sesskey()]);
    $down = new moodle_url($PAGE->url, ['action' => 'down', 'id' => $d->id, 'sesskey' => sesskey()]);

    $en = ((int)$d->enabled === 1) ? 'Да' : 'Нет';

    $actions = html_writer::link($up, '↑', ['class' => 'btn btn-outline-secondary btn-sm me-1']);
    $actions .= html_writer::link($down, '↓', ['class' => 'btn btn-outline-secondary btn-sm me-1']);
    $actions .= html_writer::link($toggle, $en === 'Да' ? 'Выключить' : 'Включить', ['class' => 'btn btn-outline-primary btn-sm']);

    $table->data[] = [
        (int)$d->sortorder,
        s($d->shortname),
        s($d->title),
        s($d->handlertype),
        $en,
        $actions,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
