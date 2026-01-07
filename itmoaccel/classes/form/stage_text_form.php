<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class stage_text_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $custom = (array)($this->_customdata ?? []);
        $can_submit = !empty($custom['can_submit']);

        $mform->addElement('textarea', 'text', '', ['rows' => 10, 'style' => 'width:100%']);
        $mform->setType('text', PARAM_TEXT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('submit', 'savebtn', get_string('save', 'local_itmoaccel'));

        if ($can_submit) {
            $mform->addElement('submit', 'submitbtn', get_string('submit_for_approval', 'local_itmoaccel'));
        }
    }
}