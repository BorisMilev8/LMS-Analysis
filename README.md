# LMS Analysis Moodle workload planner

Boris Milev's SENG 701 development component. The LMS survey report and narrated comparison video will be separate student deliverables.

## Current implementation

A Moodle Dashboard block opens a personal planner showing actionable calendar activities across enrolled, non-suspended courses for the next 90 days. Students can open the original activity and save Low, Medium, or High priorities and effort estimates in whole minutes. Filters select a course, priority, or both. Weekly summaries group action dates into Monday-to-Sunday weeks using the student's Moodle timezone. Each week shows activities, estimated minutes, missing estimates, and high-priority work. This describes work associated with action dates, not a scheduled study plan. The summary totals estimated minutes for the displayed activities and separately counts activities without an estimate. Selecting Not set and entering zero effort clears the planning record. Priorities belong to the logged-in user; instructor deadlines and grades are never modified.

Targets Moodle 4.5 and PHP 8.1 or later. Compatibility with newer versions has not been tested. This is an alpha implementation candidate, not a verified deployment or approved Moodle marketplace listing.

## Install in a test Moodle site

1. Download or clone this repository.
2. Copy `block_workloadplanner` into your Moodle installation as `blocks/workloadplanner`. Do not copy the entire repository into that directory.
3. As administrator, visit Site administration > Notifications to install the plugin database table.
4. On your Dashboard, enable editing, choose Add a block, and select Workload planner.
5. Enrol a student in two test courses with future-dated assignments or quizzes. Sign in as that student and open the planner.
6. Set a priority and effort estimate, reopen the planner, and confirm it persists. Repeat using another student account to verify that priorities remain private.

No separate API key, external service, JavaScript build, or modification of Moodle core is required.

## Upgrade from 0.1.0

Replace `blocks/workloadplanner` with the new plugin files and visit Site administration > Notifications. The upgrade adds the effort field with a default of zero and preserves existing priorities. Back up your test database before updating. Existing zero values mean not estimated.

## Download an installable ZIP

Open the repository Actions tab, select a successful PHP syntax run, and download the `workloadplanner-plugin` artifact (you may need to sign in to GitHub). Extract that artifact to obtain `workloadplanner.zip`. On a test Moodle site, use Site administration > Plugins > Install plugins to upload the inner ZIP, then complete Notifications and add the Dashboard block. The archive root is `workloadplanner`, as required by Moodle.

## Checks

GitHub Actions checks PHP syntax and runs standalone filtering, summary, and weekly timezone behavior tests. Moodle integration tests are supplied in `block_workloadplanner/tests/planner_test.php`. In a disposable Moodle development installation with PHPUnit initialized, run:

```sh
vendor/bin/phpunit blocks/workloadplanner/tests/planner_test.php
```

Use Moodle's official PHPUnit setup instructions before running that command: https://moodledev.io/general/development/tools/phpunit

See [alpha checklist](docs/ALPHA.md) for scope, pending checks, and limitations. No uptime or concurrent-user result has been measured yet.

## Technical references

- Moodle block plugins: https://moodledev.io/docs/4.5/apis/plugintypes/blocks
- Calendar API implementation used to confirm the interface: https://github.com/moodle/moodle/blob/MOODLE_405_STABLE/calendar/externallib.php
- Privacy API: https://moodledev.io/docs/4.5/apis/subsystems/privacy

## License

Plugin source is licensed under GNU GPL version 3 or later, consistent with Moodle. See https://www.gnu.org/licenses/gpl-3.0.html for the license terms.

Version 0.3.0 adds weekly summaries without changing the database. If upgrading directly from 0.1.0, the 0.2.0 effort-field migration still runs.

## Personal reminders in version 0.4.0

Open Edit planning details, enable Personal reminder, and select a time on or before the activity action date. At or after that time the reminder appears when you open the planner, even if your current filters exclude that activity. Disable the reminder to dismiss it. This is an in-planner reminder, not email/push delivery or a background notification. Only activities still available in the planner can appear. Update the plugin and run Site administration > Notifications to add the reminder field; earlier priorities and effort estimates are preserved.
