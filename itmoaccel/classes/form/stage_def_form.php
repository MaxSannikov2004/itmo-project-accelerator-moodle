<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class stage_def_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_itmoaccel'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $mform->addElement('text', 'title', get_string('title', 'local_itmoaccel'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $options = [
            'text' => 'Text',
            'files' => 'Files',
            // ai_topics обычно дефолтный, но оставим на всякий:
            'ai_topics' => 'AI Topics',
        ];
        $mform->addElement('select', 'handlertype', get_string('handlertype', 'local_itmoaccel'), $options);
        $mform->setType('handlertype', PARAM_TEXT);
        $mform->setDefault('handlertype', 'text');

        $this->add_action_buttons(false, get_string('add_stage', 'local_itmoaccel'));
    }
}
