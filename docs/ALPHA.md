# Alpha development checklist

This is an implementation inventory, not measured quality evidence. A feature is not accepted merely because its code exists.

| Planned user feature | Code status | Acceptance evidence |
| --- | --- | --- |
| Dashboard entry point | Implemented | Moodle test pending |
| Cross-course upcoming activities | Implemented | Moodle test pending |
| Activity action dates | Implemented | Moodle test pending |
| Links to original activity | Implemented | Moodle test pending |
| Select personal priority | Implemented | Moodle test pending |
| Persist and clear private priority | Implemented | Moodle test pending |
| Enter effort estimates | Implemented | Moodle persistence test pending |
| Filter by course or priority | Implemented | Standalone CI test; Moodle UI test pending |
| Weekly effort summary | Implemented | Timezone CI tests; Moodle UI test pending |
| Personal reminder scheduling | Implemented in planner | Standalone timing tests; Moodle test pending |

Ten of ten planned user features have code (100% by equal feature count). Six initial critical features are represented, but their acceptance tests have not run. This denominator is explicit; it does not imply 100% of development effort or verified completion. No planned feature lacks code (0%); integration and deployment gaps remain; the defect percentage is unknown until tests are executed.

## Pending acceptance checks

- Install on Moodle 4.5 and verify the table is created.
- Run the supplied Moodle PHPUnit tests, including user isolation, unauthorized event rejection, and privacy deletion.
- Use a student enrolled in two courses and compare dates/links with Moodle Timeline.
- Test hidden activities, restricted activities, individual/group overrides, completed work, and suspended enrolments. The plugin delegates visibility and date resolution to Moodle's calendar API; these cases still require verification.
- Verify empty state and validation messages, keyboard navigation, and small-screen layout.
- Verify a cancelled form leaves data unchanged; a request without a valid session key cannot save.
- Stability target: 30 minutes of browsing and editing without a crash or restart. Result: not measured.
- Concurrency target: five independent student sessions. Define severe degradation as core actions consistently exceeding three seconds in the agreed local test setup. Result: not measured.
- Record Moodle/PHP versions, hardware, dataset, session duration, concurrency and observed response times alongside all results.

## Known scope limits

Only future actionable calendar activities are shown, for 90 days. The planner is not a complete assignment inventory: undated work, some external-tool work, completed activities, overdue activities and activities unavailable to the student may not appear. Action dates are not universally assignment due dates. Priorities belong to a calendar event ID and may need re-entry if that event is recreated. Records for removed events are retained until the user data is deleted or the plugin is uninstalled. Reminders appear only when the planner is opened; background delivery is not included. Weekly totals group estimates by action dates; they do not allocate study time or guarantee a complete workload forecast. Calendar retrieval is capped at four pages of 50 events. Official Moodle plugin directory submission and marketplace review have not occurred.

## Testing status at initial commit

The development workspace has no PHP executable or Moodle installation. Database XML and file structure can be checked locally; PHP syntax is delegated to GitHub Actions, and Moodle integration tests require a Moodle environment. Do not report the stability/concurrency targets as achievements.

## Version 0.2.0 acceptance additions

- Upgrade an existing 0.1.0 database and confirm existing priorities survive with zero effort.
- Save effort with and without a priority; reload and confirm both fields persist.
- Reject negative, decimal, nonnumeric, and greater-than-10080 effort values.
- Clear both fields and confirm only the current user's planning record is removed.
- Combine course and priority filters; reset filters; compare the total to displayed estimates.
- Verify estimates are private and included in privacy exports.

The alpha milestone snapshot remains 0.1.0. This later candidate expands code coverage beyond the alpha range; it is not a verified beta claim. Moodle integration, stability and concurrency results remain unmeasured.

## Version 0.3.0 acceptance additions

- Change the student timezone and verify activities around Sunday/Monday midnight move into the appropriate week.
- Confirm weekly totals reflect combined course/priority filters.
- Compare each week's missing-estimate and high-priority counts against the activity rows.
- Verify installation of the generated plugin ZIP in a disposable Moodle site.

Standalone CI covers DST fallback, local week boundaries, year boundaries, unknown estimates, filtered totals and empty results. Full Moodle acceptance and stability tests are still pending.

## Version 0.4.0 reminder acceptance

All ten planned features now have implementation code; verified overall completion is still unknown. Reminder timing checks run in CI. Test upgrade from 0.3.0, reminder-only persistence, current-user isolation, disabling reminders, timezone display and rejection of times after the action date in Moodle. Reminders require opening the planner and do not send email/push. Removed, completed or no-longer-upcoming activities cannot generate a visible reminder. Earlier version inventory figures above are historical snapshots.
