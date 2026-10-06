<?php
require_once(__DIR__ . '/../../config.php');
require_login();
if (isguestuser()) {
    throw new require_login_exception();
}
$courseid = optional_param('courseid', 0, PARAM_INT);
$priority = optional_param('priority', -1, PARAM_INT);
$sort = optional_param('sort', 'deadline', PARAM_ALPHA);
if (!in_array($sort, ['deadline', 'priority', 'effort'], true)) {
    throw new invalid_parameter_exception('Invalid planner sort');
}
if ($courseid < 0 || $priority < -1 || $priority > 3) {
    throw new invalid_parameter_exception('Invalid planner filter');
}
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/blocks/workloadplanner/view.php', ['courseid' => $courseid, 'priority' => $priority, 'sort' => $sort]);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('pluginname', 'block_workloadplanner'));
$PAGE->set_heading(get_string('pluginname', 'block_workloadplanner'));
$allevents = \block_workloadplanner\planner::activities();
$priorities = \block_workloadplanner\planner::priorities();
$events = \block_workloadplanner\planning_view::filter($allevents, $priorities, $courseid, $priority);
$events = \block_workloadplanner\planning_view::sort($events, $priorities, $sort);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'block_workloadplanner'));
echo html_writer::tag('p', get_string('window', 'block_workloadplanner'));
$duereminders = \block_workloadplanner\reminders::due($allevents, $priorities, time());
if ($duereminders) {
    echo $OUTPUT->heading(get_string('remindersheading', 'block_workloadplanner'), 3);
    echo html_writer::tag('p', get_string('remindernote', 'block_workloadplanner'));
    $remindertable = new html_table();
    $remindertable->head = [get_string('activity', 'block_workloadplanner'),
        get_string('remindertime', 'block_workloadplanner'), get_string('editpriority', 'block_workloadplanner')];
    foreach ($duereminders as $event) {
        $remindertable->data[] = [
            html_writer::link(new moodle_url($event->action->url), format_string($event->name)),
            userdate($priorities[$event->id]->reminderat),
            html_writer::link(new moodle_url('/blocks/workloadplanner/edit.php', ['eventid' => $event->id]),
                get_string('editpriority', 'block_workloadplanner')),
        ];
    }
    echo html_writer::start_div('table-responsive');
    echo html_writer::table($remindertable);
    echo html_writer::end_div();
}
if (!$allevents) {
    echo $OUTPUT->notification(get_string('empty', 'block_workloadplanner'), 'info');
} else {
    $courses = [0 => get_string('allcourses', 'block_workloadplanner')];
    foreach ($allevents as $event) {
        $courses[$event->course->id] = format_string($event->course->fullname);
    }
    $priorityoptions = [-1 => get_string('allpriorities', 'block_workloadplanner')];
    for ($i = 0; $i <= 3; $i++) {
        $priorityoptions[$i] = get_string('priority' . $i, 'block_workloadplanner');
    }
    echo html_writer::start_tag('form', ['method' => 'get',
        'action' => (new moodle_url('/blocks/workloadplanner/view.php'))->out(false), 'class' => 'mb-3']);
    echo html_writer::label(get_string('course', 'block_workloadplanner'), 'planner-course');
    echo html_writer::select($courses, 'courseid', $courseid, false, ['id' => 'planner-course']);
    echo html_writer::label(get_string('priority', 'block_workloadplanner'), 'planner-priority');
    echo html_writer::select($priorityoptions, 'priority', $priority, false, ['id' => 'planner-priority']);
    $sortoptions = [];
    foreach (['deadline', 'priority', 'effort'] as $mode) {
        $sortoptions[$mode] = get_string('sort' . $mode, 'block_workloadplanner');
    }
    echo html_writer::label(get_string('sortby', 'block_workloadplanner'), 'planner-sort');
    echo html_writer::select($sortoptions, 'sort', $sort, false, ['id' => 'planner-sort']);
    echo html_writer::tag('button', get_string('filter', 'block_workloadplanner'),
        ['type' => 'submit', 'class' => 'btn btn-primary ml-2']);
    echo html_writer::link(new moodle_url('/blocks/workloadplanner/view.php'),
        get_string('resetfilters', 'block_workloadplanner'), ['class' => 'ml-2']);
    echo html_writer::end_tag('form');
    $summary = \block_workloadplanner\planning_view::summary($events, $priorities);
    echo html_writer::tag('p', get_string('summary', 'block_workloadplanner', (object)$summary));
    if (!$events) {
        echo $OUTPUT->notification(get_string('nofiltermatches', 'block_workloadplanner'), 'info');
    } else {
        echo $OUTPUT->heading(get_string('weeklyheading', 'block_workloadplanner'), 3);
        echo html_writer::tag('p', get_string('weeklynote', 'block_workloadplanner'));
        $weeklytable = new html_table();
        $weeklytable->head = array_map(function($key) {
            return get_string($key, 'block_workloadplanner');
        }, ['week', 'activitycount', 'effortminutes', 'unestimated', 'highpriority']);
        $timezone = \core_date::get_user_timezone_object();
        $weeks = \block_workloadplanner\planning_view::weekly($events, $priorities, $timezone);
        foreach ($weeks as $week) {
            $weeklytable->data[] = [
                userdate($week['start'], get_string('strftimedate', 'langconfig')) . ' - ' .
                    userdate($week['end'], get_string('strftimedate', 'langconfig')),
                $week['activities'], $week['minutes'], $week['unestimated'], $week['highpriority'],
            ];
        }
        echo html_writer::start_div('table-responsive');
        echo html_writer::table($weeklytable);
        echo html_writer::end_div();
        echo $OUTPUT->heading(get_string('activitiesheading', 'block_workloadplanner'), 3);
        $table = new html_table();
        $table->head = array_map(function($key) {
            return get_string($key, 'block_workloadplanner');
        }, ['activity', 'course', 'date', 'priority', 'effortminutes']);
        foreach ($events as $event) {
            $savedpriority = (int)($priorities[$event->id]->priority ?? 0);
            $effort = (int)($priorities[$event->id]->effortminutes ?? 0);
            $edit = new moodle_url('/blocks/workloadplanner/edit.php', ['eventid' => $event->id]);
            $table->data[] = [
                html_writer::link(new moodle_url($event->action->url), format_string($event->name)),
                format_string($event->course->fullname), userdate($event->timesort),
                get_string('priority' . $savedpriority, 'block_workloadplanner') . ' ' .
                    html_writer::link($edit, get_string('editpriority', 'block_workloadplanner')),
                $effort > 0 ? (string)$effort : get_string('noestimate', 'block_workloadplanner'),
            ];
        }
        echo html_writer::start_div('table-responsive');
        echo html_writer::table($table);
        echo html_writer::end_div();
    }
    if (count($allevents) >= \block_workloadplanner\planner::LIMIT) {
        echo $OUTPUT->notification(get_string('limited', 'block_workloadplanner'), 'info');
    }
}
echo $OUTPUT->footer();
