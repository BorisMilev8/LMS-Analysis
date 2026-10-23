<?php
require_once(__DIR__ . '/../../config.php');
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$eventid = required_param('eventid', PARAM_INT);
// Set up the personal page before the calendar API initializes the renderer.
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/blocks/workloadplanner/details.php', ['eventid' => $eventid]);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('assignmentdetails', 'block_workloadplanner'));
$PAGE->set_heading(get_string('assignmentdetails', 'block_workloadplanner'));
$details = \block_workloadplanner\assignment_details::load($eventid);
$assignment = $details['assignment'];
$instance = $assignment->get_instance();
$context = $details['context'];
$title = format_string($instance->name, true, ['context' => $context]);
$PAGE->navbar->add(get_string('pluginname', 'block_workloadplanner'),
    new moodle_url('/blocks/workloadplanner/view.php'));
$PAGE->navbar->add(get_string('assignmentdetails', 'block_workloadplanner'));
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('course', 'block_workloadplanner') . ': ' .
    format_string($details['course']->fullname, true, ['context' => $context]));
echo html_writer::tag('p', get_string('planneractiondate', 'block_workloadplanner') . ': ' .
    userdate($details['event']->timesort));
echo html_writer::tag('p', get_string('actiondateexplanation', 'block_workloadplanner'));
echo $OUTPUT->heading(get_string('assignmentinstructions', 'block_workloadplanner'), 3);
if (!$assignment->show_intro()) {
    echo $OUTPUT->notification(get_string('instructionsnotreleased', 'block_workloadplanner'), 'info');
} else if (trim($instance->intro) === '') {
    echo $OUTPUT->notification(get_string('noinstructions', 'block_workloadplanner'), 'info');
} else {
    // Moodle's formatter handles rich text and embedded file URLs in the module context.
    echo format_module_intro('assign', $instance, $details['cm']->id);
}
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/mod/assign/view.php', ['id' => $details['cm']->id]),
    get_string('openassignment', 'block_workloadplanner'), ['class' => 'btn btn-primary']));
echo html_writer::tag('p', html_writer::link(new moodle_url('/blocks/workloadplanner/view.php'),
    get_string('backtoplanner', 'block_workloadplanner')));
echo $OUTPUT->footer();
