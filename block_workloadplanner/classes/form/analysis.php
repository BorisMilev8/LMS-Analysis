<?php
namespace block_workloadplanner\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class analysis extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'eventid');
        $mform->setType('eventid', PARAM_INT);
        $draft = $this->_customdata['draft'];
        if ($draft) {
            $mform->addElement('hidden', 'token', $draft['token']);
            $mform->setType('token', PARAM_ALPHANUM);
            $mform->addElement('text', 'effortminutes', get_string('effortminutes', 'block_workloadplanner'));
            $mform->setType('effortminutes', PARAM_RAW_TRIMMED);
            $options = [];
            for ($i = 1; $i <= 3; $i++) {
                $options[$i] = get_string('priority' . $i, 'block_workloadplanner');
            }
            $mform->addElement('select', 'priority', get_string('priority', 'block_workloadplanner'), $options);
            $mform->setType('priority', PARAM_INT);
            $this->add_action_buttons(true, get_string('applyanalysis', 'block_workloadplanner'));
        } else {
            $mform->addElement('textarea', 'instructions', get_string('assignmenttext', 'block_workloadplanner'),
                ['rows' => 12, 'cols' => 80]);
            $mform->setType('instructions', PARAM_RAW_TRIMMED);
            $options = [];
            foreach (['beginner', 'some', 'confident'] as $key) {
                $options[$key] = get_string('experience' . $key, 'block_workloadplanner');
            }
            $mform->addElement('select', 'experience', get_string('experience', 'block_workloadplanner'), $options);
            $mform->setType('experience', PARAM_ALPHA);
            $mform->setDefault('experience', 'some');
            $mform->addElement('static', 'policy', get_string('aiusagepolicy', 'core_ai'), get_string('userpolicy', 'core_ai'));
            $mform->addElement('advcheckbox', 'consent', get_string('analysisconsent', 'block_workloadplanner'));
            $mform->setType('consent', PARAM_BOOL);
            $this->add_action_buttons(true, get_string('analyzeassignment', 'block_workloadplanner'));
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($this->_customdata['draft']) {
            if (!preg_match('/^[0-9]{1,5}$/D', (string)($data['effortminutes'] ?? ''))
                    || (int)$data['effortminutes'] < 1 || (int)$data['effortminutes'] > 10080) {
                $errors['effortminutes'] = get_string('analysisinvalideffort', 'block_workloadplanner');
            }
            if (!in_array((int)$data['priority'], [1, 2, 3], true)) {
                $errors['priority'] = get_string('invalidpriority', 'block_workloadplanner');
            }
        } else {
            if (trim($data['instructions'] ?? '') === '' || strlen($data['instructions']) > 24000) {
                $errors['instructions'] = get_string('analysisinputlength', 'block_workloadplanner');
            }
            if (empty($data['consent'])) {
                $errors['consent'] = get_string('analysisconsentrequired', 'block_workloadplanner');
            }
            if (!in_array($data['experience'] ?? '', ['beginner', 'some', 'confident'], true)) {
                $errors['experience'] = get_string('analysisexperienceinvalid', 'block_workloadplanner');
            }
        }
        return $errors;
    }
}
