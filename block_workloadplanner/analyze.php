<?php
require_once(__DIR__ . '/../../config.php');
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$eventid = required_param('eventid', PARAM_INT);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/blocks/workloadplanner/analyze.php', ['eventid' => $eventid]);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('analyzeassignment', 'block_workloadplanner'));
$PAGE->set_heading(get_string('pluginname', 'block_workloadplanner'));
$source = \block_workloadplanner\assignment_analysis::source($eventid);
$back = new moodle_url('/blocks/workloadplanner/view.php');
$plans = \block_workloadplanner\planner::priorities();
$draft = $SESSION->block_workloadplanner_draft ?? null;
if ($draft && time() - $draft['created'] > 1800) {
    unset($SESSION->block_workloadplanner_draft);
    $draft = null;
}
if ($draft && (int)$draft['eventid'] !== $eventid) {
    $draft = null;
}
$form = new \block_workloadplanner\form\analysis($PAGE->url, ['draft' => $draft]);
$error = '';
if ($form->is_cancelled()) {
    unset($SESSION->block_workloadplanner_draft);
    redirect($back);
} else if ($data = $form->get_data()) {
    require_sesskey();
    if ((int)$data->eventid !== $eventid) {
        throw new invalid_parameter_exception('Event mismatch');
    }
    if ($draft) {
        if (!\block_workloadplanner\analysis_rules::draft_valid($draft, (string)($data->token ?? ''),
                $source['hash'], $plans[$eventid] ?? null, time())) {
            unset($SESSION->block_workloadplanner_draft);
            redirect($back, get_string('analysisstale', 'block_workloadplanner'), null,
                \core\output\notification::NOTIFY_WARNING);
        }
        // Access is rechecked by save. Reminder stays unchanged; existing values change only on explicit confirmation.
        \block_workloadplanner\planner::save($eventid, (int)$data->priority, (int)$data->effortminutes);
        unset($SESSION->block_workloadplanner_draft);
        redirect($back, get_string('analysisapplied', 'block_workloadplanner'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else if (!\block_workloadplanner\assignment_analysis::available()) {
        $error = get_string('analysisunavailable', 'block_workloadplanner');
    } else if (time() - (int)($SESSION->block_workloadplanner_lastanalysis ?? 0) < 60) {
        $error = get_string('analysisratelimit', 'block_workloadplanner');
    } else {
        $SESSION->block_workloadplanner_lastanalysis = time();
        // Checkbox explicitly acknowledges Moodle's policy and this external text transfer at point of use.
        if (!\core_ai\manager::get_user_policy_status($USER->id)) {
            \core_ai\manager::user_policy_accepted($USER->id, $source['context']->id);
        }
        try {
            $result = \block_workloadplanner\assignment_analysis::generate($source['title'],
                $data->instructions, $data->experience, $source['context']->id);
            $otherminutes = 0;
            foreach ($source['events'] as $other) {
                if ((int)$other->id !== $eventid && $other->timesort <= time() + 7 * DAYSECS) {
                    $otherminutes += (int)($plans[$other->id]->effortminutes ?? 0);
                }
            }
            $result['priority'] = \block_workloadplanner\analysis_rules::priority(
                (int)$source['event']->timesort, $result['effortminutes'], $otherminutes,
                \block_workloadplanner\assignment_analysis::threshold(), time());
            // Keep one transient result per session; refresh never makes a second AI call.
            $SESSION->block_workloadplanner_draft = $result + [
                'eventid' => $eventid, 'created' => time(), 'token' => bin2hex(random_bytes(16)),
                'sourcehash' => $source['hash'],
                'snapshot' => \block_workloadplanner\analysis_rules::snapshot($plans[$eventid] ?? null),
            ];
            redirect($PAGE->url);
        } catch (\moodle_exception $e) {
            $safeerrors = ['analysisunavailable', 'analysisconsentrequired', 'analysisfailed', 'analysisinvalid'];
            $key = $e->module === 'block_workloadplanner' && in_array($e->errorcode, $safeerrors, true)
                ? $e->errorcode : 'analysisfailed';
            $error = get_string($key, 'block_workloadplanner');
        } catch (\Throwable $e) {
            // Do not disclose provider errors, credentials or submitted instructions in the browser.
            $error = get_string('analysisfailed', 'block_workloadplanner');
        }
    }
}
$form->set_data($draft ? ['eventid' => $eventid, 'effortminutes' => $draft['effortminutes'],
    'priority' => $draft['priority']] : ['eventid' => $eventid, 'instructions' => $source['instructions']]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('analyzeassignment', 'block_workloadplanner'));
echo html_writer::tag('h3', s($source['title']));
echo html_writer::tag('p', get_string('analysisnotice', 'block_workloadplanner'));
if ($error) {
    echo $OUTPUT->notification($error, 'error');
}
if ($draft) {
    echo $OUTPUT->heading(get_string('analysisresult', 'block_workloadplanner'), 3);
    echo html_writer::tag('p', s($draft['summary']));
    echo html_writer::tag('p', get_string('analysisrange', 'block_workloadplanner', (object)$draft));
    echo html_writer::tag('p', s($draft['assumptions']));
    echo html_writer::tag('p', get_string('analysispriorityrule', 'block_workloadplanner',
        \block_workloadplanner\assignment_analysis::threshold()));
    echo $OUTPUT->notification(get_string('analysisoverwrite', 'block_workloadplanner'), 'info');
    $form->display();
} else if (\block_workloadplanner\assignment_analysis::available()) {
    $form->display();
} else {
    echo $OUTPUT->notification(get_string('analysisunavailable', 'block_workloadplanner'), 'info');
}
echo html_writer::link($back, get_string('backtoplanner', 'block_workloadplanner'));
echo $OUTPUT->footer();
