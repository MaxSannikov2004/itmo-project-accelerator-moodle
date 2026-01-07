<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class stage_files_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('filemanager', 'files_draft', '', null, [
            'subdirs' => 0,
            'maxfiles' => 10,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL,
        ]);
        $mform->addRule('files_draft', null, 'required', null, 'client');

        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('submit', 'savebtn', get_string('save', 'local_itmoaccel'));
        $mform->addElement('submit', 'submitbtn', get_string('submit_for_approval', 'local_itmoaccel'));
    }
}
