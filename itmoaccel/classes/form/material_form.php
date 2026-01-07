<?php
namespace local_itmoaccel\form;

defined('MOODLE_INTERNAL') || die();
require_once($GLOBALS['CFG']->libdir . '/formslib.php');

class material_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'title', get_string('material_title', 'local_itmoaccel'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('filepicker', 'materialfile', get_string('material_file', 'local_itmoaccel'), null, [
            'accepted_types' => ['.pdf', '*'],
        ]);
        $mform->addRule('materialfile', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('add_material', 'local_itmoaccel'));
    }
}
