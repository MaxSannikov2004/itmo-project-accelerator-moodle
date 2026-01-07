<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/itmoaccel:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/itmoaccel/manage_materials.php'));
$PAGE->set_title(get_string('manage_materials', 'local_itmoaccel'));
$PAGE->set_heading(get_string('manage_materials', 'local_itmoaccel'));

global $DB;

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action !== '') {
    require_sesskey();

    if ($action === 'toggle' && $id) {
        $rec = $DB->get_record('local_itmoaccel_materials', ['id' => $id], '*', MUST_EXIST);
        $rec->enabled = ((int)$rec->enabled === 1) ? 0 : 1;
        $rec->timemodified = time();
        $DB->update_record('local_itmoaccel_materials', $rec);
        redirect($PAGE->url);
    }

    if (($action === 'up' || $action === 'down') && $id) {
        $rec = $DB->get_record('local_itmoaccel_materials', ['id' => $id], '*', MUST_EXIST);
        $all = $DB->get_records('local_itmoaccel_materials', null, 'sortorder ASC, id ASC');
        $ids = array_values(array_map(fn($x) => (int)$x->id, $all));
        $pos = array_search((int)$rec->id, $ids, true);

        if ($pos !== false) {
            $swappos = ($action === 'up') ? $pos - 1 : $pos + 1;
            if ($swappos >= 0 && $swappos < count($ids)) {
                $other = $DB->get_record('local_itmoaccel_materials', ['id' => $ids[$swappos]], '*', MUST_EXIST);

                $tmp = (int)$rec->sortorder;
                $rec->sortorder = (int)$other->sortorder;
                $other->sortorder = $tmp;
                $rec->timemodified = time();
                $other->timemodified = time();
                $DB->update_record('local_itmoaccel_materials', $rec);
                $DB->update_record('local_itmoaccel_materials', $other);
            }
        }
        redirect($PAGE->url);
    }

    if ($action === 'delete' && $id) {
        // удалить файл(ы) и запись
        $fs = get_file_storage();
        $sysctx = context_system::instance();
        $fs->delete_area_files($sysctx->id, 'local_itmoaccel', 'material', $id);
        $DB->delete_records('local_itmoaccel_materials', ['id' => $id]);
        redirect($PAGE->url);
    }
}

$form = new \local_itmoaccel\form\material_form(null);
$notice = '';
$error = '';

if ($data = $form->get_data()) {
    require_sesskey();

    $title = trim((string)$data->title);
    $draftid = (int)$data->materialfile;

    $max = $DB->get_field_sql("SELECT COALESCE(MAX(sortorder), 0) FROM {local_itmoaccel_materials}");
    $now = time();
    $materialid = $DB->insert_record('local_itmoaccel_materials', (object)[
        'title' => $title,
        'sortorder' => ((int)$max) + 10,
        'enabled' => 1,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    // Сохранить файл из draft area в pluginfile area.
    $options = [
        'subdirs' => 0,
        'maxfiles' => 1,
        'accepted_types' => ['.pdf', '*'],
    ];
    file_save_draft_area_files($draftid, $context->id, 'local_itmoaccel', 'material', $materialid, $options);

    $notice = 'Материал добавлен.';
}

$materials = $DB->get_records('local_itmoaccel_materials', null, 'sortorder ASC, id ASC');

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('manage_materials', 'local_itmoaccel'));

if ($notice) echo $OUTPUT->notification(s($notice), 'notifysuccess');
if ($error) echo $OUTPUT->notification(s($error), 'notifyproblem');

echo html_writer::tag('h3', get_string('add_material', 'local_itmoaccel'));
$form->display();

echo html_writer::tag('h3', get_string('materials', 'local_itmoaccel'), ['class' => 'mt-4']);

if (!$materials) {
    echo html_writer::div('Пока нет материалов.', 'itmo-muted');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = ['#', get_string('material_title', 'local_itmoaccel'), get_string('enabled', 'local_itmoaccel'), ''];
$table->data = [];

foreach ($materials as $m) {
    $en = ((int)$m->enabled === 1) ? 'Да' : 'Нет';
    $toggle = new moodle_url($PAGE->url, ['action' => 'toggle', 'id' => $m->id, 'sesskey' => sesskey()]);
    $up = new moodle_url($PAGE->url, ['action' => 'up', 'id' => $m->id, 'sesskey' => sesskey()]);
    $down = new moodle_url($PAGE->url, ['action' => 'down', 'id' => $m->id, 'sesskey' => sesskey()]);
    $del = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $m->id, 'sesskey' => sesskey()]);

    $actions = html_writer::link($up, '↑', ['class' => 'btn btn-outline-secondary btn-sm me-1']);
    $actions .= html_writer::link($down, '↓', ['class' => 'btn btn-outline-secondary btn-sm me-1']);
    $actions .= html_writer::link($toggle, $en === 'Да' ? 'Выключить' : 'Включить', ['class' => 'btn btn-outline-primary btn-sm me-1']);
    $actions .= html_writer::link($del, 'Удалить', ['class' => 'btn btn-outline-danger btn-sm']);

    $table->data[] = [
        (int)$m->sortorder,
        s($m->title),
        $en,
        $actions,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
