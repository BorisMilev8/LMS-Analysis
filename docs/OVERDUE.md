# Overdue unfinished assignments

Version 0.11.0 adds a separate overdue section to the planner. This is implementation documentation; live acceptance is pending.

## Scope and rules

- Online Moodle assignments in visible courses where the current user has active enrollment, module visibility and assignment view/submit capabilities.
- Effective due date from Moodle's assignment instance after applying its user/group override rules and relative course dates. A later personal extension takes precedence.
- Due dates strictly before now, within the past 90 days. No due date is not overdue. Cutoff dates are not substituted for deadlines; visible closed assignments can still appear, with a link to verify whether late submission is allowed.
- Latest submitted individual or team attempts are excluded. Drafts and reopened attempts remain unfinished. Missing/ambiguous submission groups are excluded rather than reading another team's submission.
- Personal Completed items are excluded from the overdue section. This remains separate from actual submission. Existing plan event IDs are reused, preferring the most recently modified private plan when an assignment has multiple override events.
- Only the first 200 visible, eligible assignment modules are examined, ordered by course ID and module order. A warning appears if the scan stops early. Assignments without a due calendar event and offline-only assignments are outside this version's scope.
- Course and priority filters and sorting apply. The Next 7 days filter affects upcoming work only. Overdue entries do not add past effort to upcoming weekly totals.
- Reads use Moodle's non-creating submission/flag APIs. No grades, submissions or activity-completion state are changed. Normal planner loading does not call AI.
- An overdue read failure fails the complete refresh, with no partial success timestamp or totals.

## Install and verify

Update the plugin files and allow Moodle's normal upgrade/Notifications process to register 0.11.0. There is no new schema change beyond the 0.10.0 progress migration; previous versions still run that migration.

Use synthetic test courses and separate student browser profiles:

1. Add a visible online assignment due yesterday and leave it unsubmitted. Confirm its name, course and actual deadline under Overdue unfinished assignments, with both a text label and highlighted row.
2. Change its personal progress to In progress, save and reload. Then set Completed and confirm it disappears from that student's overdue list while another student's unfinished view remains independent.
3. Give the student a future extension. Confirm the assignment disappears from overdue. Repeat with user/group deadline overrides and check the displayed effective date.
4. Submit the assignment in Moodle and confirm it disappears; drafts/reopened latest attempts should remain unfinished.
5. Hide or restrict the module, or remove/suspend course enrollment. Confirm it cannot appear or be changed through an old editing link.
6. Combine course/priority filters and sorting, then select Next 7 days. Overdue work matching course/priority stays visible; upcoming totals remain separate.
7. Recheck reminders, AI apply, saved priorities, keyboard operation and narrow-screen table scrolling.

Standalone adapter tests cover these decision paths using doubles. Moodle integration tests exercise real module access, overrides, individual/team submissions, progress isolation and editing. Live browser acceptance must be recorded separately.
