<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class assign_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'student_email', get_string('student_email', 'local_itmoaccel'));
        $mform->setType('student_email', PARAM_EMAIL);

        $mform->addElement('text', 'supervisor_email', get_string('supervisor_email', 'local_itmoaccel'));
        $mform->setType('supervisor_email', PARAM_EMAIL);

        $mform->addElement('submit', 'assignbtn', get_string('assign', 'local_itmoaccel'));
    }
}
