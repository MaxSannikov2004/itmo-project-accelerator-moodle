<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class import_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'importfile', get_string('import_file', 'local_itmoaccel'), null, [
            'accepted_types' => ['.csv', '.xlsx'],
        ]);
        $mform->addRule('importfile', null, 'required', null, 'client');

        $mform->addElement('submit', 'importbtn', get_string('import_run', 'local_itmoaccel'));
    }
}
