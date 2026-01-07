<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class profile_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'school', get_string('school', 'local_itmoaccel'));
        $mform->setType('school', PARAM_TEXT);

        $mform->addElement('text', 'class', get_string('class', 'local_itmoaccel'));
        $mform->setType('class', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('save', 'local_itmoaccel'));
    }
}
