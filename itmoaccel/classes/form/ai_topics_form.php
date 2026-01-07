<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class ai_topics_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('textarea', 'prompt', get_string('ai_prompt', 'local_itmoaccel'), ['rows' => 4, 'style' => 'width:100%']);
        $mform->setType('prompt', PARAM_TEXT);

        $mform->addElement('text', 'count', 'Count', ['size' => 5]);
        $mform->setType('count', PARAM_INT);
        $mform->setDefault('count', 8);

        $mform->addElement('submit', 'generatebtn', get_string('generate', 'local_itmoaccel'));
    }
}
