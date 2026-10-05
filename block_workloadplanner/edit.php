<?php
require_once(__DIR__ . '/../../config.php');
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$eventid = required_param('eventid', PARAM_INT);
$url = new moodle_url('/blocks/workloadplanner/view.php');
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/blocks/workloadplanner/edit.php', ['eventid' => $eventid]);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('editpriority', 'block_workloadplanner'));
$PAGE->set_heading(get_string('pluginname', 'block_workloadplanner'));
$events = \block_workloadplanner\planner::activities();
if (!isset($events[$eventid])) {
    throw new moodle_exception('unavailable', 'block_workloadplanner');
}
$form = new \block_workloadplanner\form\priority($PAGE->url);
if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    require_sesskey();
    if ((int)$data->eventid !== $eventid) {
        throw new invalid_parameter_exception('Event mismatch');
    }
    \block_workloadplanner\planner::save($eventid, (int)$data->priority, (int)$data->effortminutes);
    redirect($url, get_string('saved', 'block_workloadplanner'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
$priorities = \block_workloadplanner\planner::priorities();
$form->set_data([
    'eventid' => $eventid,
    'priority' => $priorities[$eventid]->priority ?? 0,
    'effortminutes' => $priorities[$eventid]->effortminutes ?? 0,
]);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($events[$eventid]->name));
$form->display();
echo $OUTPUT->footer();
