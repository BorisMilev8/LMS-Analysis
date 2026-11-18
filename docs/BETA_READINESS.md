# Beta readiness and remaining work — Moodle Workload Planner

Updated: 2026-11-17. This is a **plan and evidence register**, not a statement of completed testing or an official Beta submission.

## Baseline
- Plugin: `block_workloadplanner`, release `0.9.0`, `MATURITY_ALPHA` in `block_workloadplanner/version.php`.
- Repository implementation currently provides a Dashboard entry point, current user's upcoming actionable calendar events (up to 90 days / 200 events), course/priority filters, due/action-date/priority/effort sorting, a rolling next-seven-days filter, assignment details, user-specific priority and manual effort saving, weekly effort totals, in-planner reminders, configurable AI analysis and workload warnings.
- The README and `docs/ALPHA.md` describe parts of acceptance testing as pending. Code presence is **not** live acceptance.
- The original proposal's Beta checkpoint date was Nov 9, 2026 (past as of this register); confirm any resubmission/extension requirements with the instructor.

## Remaining functionality against proposal
| Feature / requirement | Current evidence | Next action | Result |
|---|---|---|---|
| Weekly workload view | Code in `view.php` | Test timezone boundaries and totals against sample course events | Not yet verified here |
| Saved priorities and effort estimates | `planner::save` and PHPUnit cases | Verify save/clear, refresh, second student isolation | Not yet verified here |
| Personal progress tracking | No verified completed progress implementation in files inspected | Define minimum progress data model and UI; implement and test | Pending |
| Overdue highlighting | Current feed starts at `time()`, so overdue events are excluded | Design permission-safe overdue data retrieval and visually distinguish overdue items; test completed/hidden work | Pending |
| Mobile & keyboard support | Responsive table wrapper exists | Manual phone-width, zoom, tab order, focus/labels, keyboard-only test | Pending |
| Gemini/Moodle AI estimate | AI analysis documentation and workflow in repo | Verify configured provider, consent, estimate review/apply, errors, no secrets exposed | Not yet verified here |
| Next seven days | `window=week` branch in `view.php` | Boundary, combined-filter and reset checks | Not yet verified here |
| Security/privacy | Session-scoped user IDs and tests exist | Run Moodle PHPUnit and test unauthorized/hidden/unenrolled access | Not yet verified here |

## Minimum reproducible test setup
1. Record Moodle version, PHP version, plugin release/commit, site URL type (local/hosted), date/timezone, browser, hardware and test data sizes.
2. Enroll Student A in two test courses with three upcoming assignments (one within seven days), one completed assignment, one overdue assignment, and one restricted/hidden assignment; create Student B enrolled in only one course.
3. Sign in as Student A; verify planner and assignments match the course activity and Moodle calendar action dates (which may differ from submission deadlines).
4. Filter by course, priority and Next 7 days; combine filters, sort by all three modes, reset, verify totals and empty state.
5. Save High priority and 90-minute estimate; reload and verify. Clear both; verify deletion. Confirm Student B cannot see Student A's saved plans.
6. View assignment details, follow source links; confirm restricted/unreleased/unenrolled content is protected.
7. Configure a Moodle AI provider only in a safe test environment; test consent, valid estimate, edit/apply, cancel, stale draft and error fallback. Do not send real student information.
8. Test reminder timing, clearing and weekly warning across filtered views.
9. Test keyboard only, narrow/mobile viewport and 200% zoom; record defects and screenshots.
10. Record 30-minute session, concurrency (up to five independent test users if environment allows), and concrete response timings; don't claim performance targets without measurements.

## Test results log
| Test case | Environment | Expected | Observed | Pass/fail | Evidence link | Defect |
|---|---|---|---|---|---|---|
| Assignment list/access | Pending | Current accessible actionable events only | — | Not run | — | — |
| Priority/effort persistence | Pending | Student-specific changes survive reload | — | Not run | — | — |
| Filter/sort/week | Pending | Correct order and totals | — | Not run | — | — |
| AI review/apply and failure | Pending | Explicit consent, safe failure, no unintended writes | — | Not run | — | — |
| Mobile/keyboard | Pending | Operable at narrow widths and by keyboard | — | Not run | — | — |
| Stability/concurrency | Pending | Measured 30-minute session and concurrent use | — | Not run | — | — |

## Beta evidence package to assemble
- Beta report: scope, requirements traceability, implemented vs tested features, defects, performance, security and accessibility, sponsor feedback, changes since Alpha, limitations, next steps, truthful completion estimate.
- Source-only ZIP of the plugin, with build/install/upgrade instructions, repository commit/history and code statistics.
- Verified hosted or instructor-accessible application URL if required; verify login/access before sharing.
- Demo, architecture/design and deployment recordings if required by the applicable handbook and instructor; verify current requirements and submission formats.
- Cite any external/AI assistance according to course rules; do not report planned work as completed.

## Suggested order
1. Run existing test suite and manual acceptance matrix; capture evidence and defects.
2. Implement personal progress tracking and overdue highlighting only after designing the user-visible semantics and permissions.
3. Fix tests and accessibility defects, improve AI flow as needed, then re-run regression tests.
4. Update version/maturity only after acceptance evidence is available; build artifact and complete Beta report.
