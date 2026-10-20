# AI assignment analysis and workload warnings (0.6.0)

## Setup

1. Back up your database and moodledata. Update only `blocks/workloadplanner`, then visit Site administration > Notifications. The version bump adds an analysis capability; no planning-table migration is required.
2. Under Site administration > General > AI, configure and enable a provider supporting Generate text. Credentials stay in Moodle's provider settings. Provider charges may apply. See [Moodle's AI setup documentation](https://docs.moodle.org/405/en/AI_subsystem).
3. Under Plugins > Blocks > Workload planner, enable AI assignment analysis. Set the weekly warning threshold if desired (default 600 minutes; allowed 60–10080, invalid values fall back to 600).
4. As an enrolled student, choose Analyze assignment in the planner. Review/edit the prefilled instructions, select experience, read the AI policy and acknowledge the external transfer.
5. Review the summary, effort range, assumptions and suggested priority. Edit the estimate/priority and Apply to my planner. This replaces existing effort and priority explicitly, preserving the reminder. Cancel changes nothing. Existing weekly totals, filters and sorting use the saved values.

Without a configured provider, the core planner, manual estimates and workload warnings still work. No heuristic is presented as an AI response. A live provider and browser walkthrough are still required before claiming end-to-end acceptance.

## Data and limits

- Only assignments with currently accessible calendar events in the next-90-day planner are supported. Enrollment, module visibility, capabilities and assignment intro-release restrictions are checked on every request.
- Text only: title, reviewed instructions (up to 24000 bytes) and experience category. No automatic attachment/rubric parsing, submissions, names, grades or other students' data. Users may paste relevant text they have permission to send; remove personal/confidential content first.
- Moodle core records prompts, responses and policy acceptance under its AI privacy provider. External provider retention/privacy rules apply. This tool summarizes requirements; it does not solve or submit assignments.
- One review draft is held in the user session for 30 minutes, replaced by a new analysis. No persistent chat history is created. Refresh does not call AI again. Only the reviewed effort/priority is stored in the planner table. Expired drafts, changed original instructions or changed planning details are rejected rather than overwriting newer work.
- Requests use Moodle forms and session keys. A 60-second session cooldown limits repeated analysis; configured Moodle provider rate limits also apply. This is not a site-wide budget cap. Configure provider budgets/rate limits before broad access.
- Provider failure or invalid JSON never changes a plan. Output strings and integer effort range (1–10080 minutes) are validated; unknown fields are ignored. AI output is escaped plain text. AI runs only on an explicit POST, never on Dashboard/planner loads.

## Transparent planning rules

The model estimates effort. Local rules suggest priority using Moodle action dates:

| Condition | Priority |
| --- | --- |
| Within 48 hours; or within seven days when this estimate plus other estimated work in the next seven days exceeds the threshold | High |
| Within seven days; or effort at least 240 minutes | Medium |
| Otherwise | Low |

Action dates are not always final deadlines. The rules do not know grades, assignment weight, remaining work or individual availability. Unestimated work is excluded. Students can edit every suggestion before saving.

Warnings compare all accessible planner activities to the weekly threshold in the student's timezone, Monday to Sunday, independent of filters. They count unknown estimates and suggest starting earlier or splitting sessions. They group work by action week, not actual study time; they do not automatically schedule sessions or claim the threshold is a validated personal capacity.

## Verification

Run `php tests/analysis_test.php` for malformed output/range validation, priority boundaries, warning thresholds, unknown work, draft expiry and protection against stale/manual edits. Moodle tests in `block_workloadplanner/tests/planner_test.php` cover assignment access, hidden instructions, disabled-provider behavior and existing plan preservation. Tests never contact a paid provider.

Manual checks on the updated local Moodle site remain necessary:

- Configure a provider, analyze a sample report, edit/apply values, refresh, check summaries and sorting.
- Cancel an analysis with an existing plan: values must remain unchanged. Apply a new analysis: reminder must survive.
- Edit planning details in another tab after analysis: the old draft must be rejected. Also test expiration, invalid output and provider failure.
- Try another student, an unenrolled user, a restricted module and an unreleased assignment description.
- Exceed a small temporary threshold across two courses. Filter to one course: the all-course warning must remain. Reset the threshold afterward.
- Confirm ordinary planner loads send no AI requests. File upload, live chat, personalized scheduling and native Moodle App support remain out of scope for 0.6.0.

Reference: [Moodle 4.5 AI subsystem API](https://moodledev.io/docs/4.5/apis/subsystems/ai).
