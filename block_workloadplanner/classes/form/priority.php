<?php
namespace block_workloadplanner\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');
class priority extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'eventid');
        $mform->setType('eventid', PARAM_INT);
        $options = [];
        for ($i = 0; $i <= 3; $i++) {
            $options[$i] = get_string('priority' . $i, 'block_workloadplanner');
        }
        $mform->addElement('select', 'priority', get_string('priority', 'block_workloadplanner'), $options);
        $mform->setType('priority', PARAM_INT);
        $mform->addElement('text', 'effortminutes', get_string('effortminutes', 'block_workloadplanner'));
        $mform->setType('effortminutes', PARAM_RAW_TRIMMED);
        $mform->setDefault('effortminutes', '0');
        $mform->addHelpButton('effortminutes', 'effortminutes', 'block_workloadplanner');
        $mform->addElement('date_time_selector', 'reminderat',
            get_string('reminderat', 'block_workloadplanner'), ['optional' => true]);
        $mform->addHelpButton('reminderat', 'reminderat', 'block_workloadplanner');
        $this->add_action_buttons();
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['priority'] < 0 || $data['priority'] > 3) {
            $errors['priority'] = get_string('invalidpriority', 'block_workloadplanner');
        }
        $effort = (string)($data['effortminutes'] ?? '');
        if (!preg_match('/^[0-9]{1,5}$/D', $effort) || (int)$effort > 10080) {
            $errors['effortminutes'] = get_string('invalideffort', 'block_workloadplanner');
        }
        if (!\block_workloadplanner\reminders::valid((int)($data['reminderat'] ?? 0),
                (int)$this->_customdata['actiondate'])) {
            $errors['reminderat'] = get_string('invalidreminder', 'block_workloadplanner');
        }
        return $errors;
    }
}
