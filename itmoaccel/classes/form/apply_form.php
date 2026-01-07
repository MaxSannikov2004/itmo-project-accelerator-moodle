<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class apply_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $custom = (array)($this->_customdata ?? []);
        $supervisors = $custom['supervisors'] ?? [];

        $mform->addElement('text', 'school', get_string('school', 'local_itmoaccel'));
        $mform->setType('school', PARAM_TEXT);

        $mform->addElement('text', 'class', get_string('class', 'local_itmoaccel'));
        $mform->setType('class', PARAM_TEXT);

        $mform->addElement('text', 'code', 'Код руководителя (если есть)');
        $mform->setType('code', PARAM_ALPHANUMEXT);

        $mform->addElement('select', 'supervisorid', 'Выбрать руководителя из списка', $supervisors);
        $mform->setType('supervisorid', PARAM_INT);

        $mform->addElement('textarea', 'message', 'Комментарий/сообщение', ['rows' => 4, 'style' => 'width:100%']);
        $mform->setType('message', PARAM_TEXT);

        $this->add_action_buttons(false, 'Отправить заявку');
    }

    public function validation($data, $files) {
    $errors = parent::validation($data, $files);

    $code = strtoupper(trim((string)($data['code'] ?? '')));
    $supervisorid = (int)($data['supervisorid'] ?? 0);

    // Нужно либо код, либо выбор руководителя.
    if ($code === '' && $supervisorid <= 0) {
        $msg = 'Введите код руководителя или выберите руководителя из списка.';
        $errors['code'] = $msg;
        $errors['supervisorid'] = $msg;
        return $errors;
    }

    // Лёгкая проверка кода (по желанию)
    if ($code !== '' && strlen($code) < 4) {
        $errors['code'] = 'Код слишком короткий.';
    }

    return $errors;
    }

}
