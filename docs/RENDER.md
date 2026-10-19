# Render deployment for Moodle

This is a Docker deployment setup for a single Moodle demonstration site, not a claim that a live Render deployment exists. It bundles the workload planner into Moodle 4.5, with PostgreSQL 17 and PHP 8.3. The Moodle source commit is pinned in the Dockerfile. Review security updates before sharing the site beyond a capstone demonstration.

## Resources and cost

The Blueprint requests a paid 2 GB web service, a 5 GB persistent moodledata disk, and a small paid PostgreSQL instance with 5 GB database storage in Virginia. Review Render's current cost estimate before creating it. No paid resources are created by adding these files to GitHub. The free web tier cannot mount the required disk, and free databases expire.

## Create the site

1. Sign in to Render and choose New > Blueprint.
2. Connect BorisMilev8/LMS-Analysis, select main, and use the root `render.yaml`.
3. Review the resource names, region and paid plans. Change names before creating if they conflict with existing resources. Keep the service and database in the same region.
4. Enter `MOODLE_ADMIN_EMAIL` and a new `MOODLE_ADMIN_PASSWORD` with at least 12 characters, uppercase/lowercase letters, a number and a symbol. Do not reuse the local demonstration password. The admin username defaults to `admin`.
5. Review the charges, then create the Blueprint when ready. Watch build and startup logs. The first startup installs Moodle before accepting HTTP traffic; do not interrupt it or repeatedly start parallel installations.
6. Open the service's HTTPS `onrender.com` URL and sign in. The canonical site URL is taken from Render's `RENDER_EXTERNAL_URL`. For a custom domain set `MOODLE_WWWROOT` to its HTTPS root URL before restarting.
7. After successful installation, remove `MOODLE_ADMIN_PASSWORD` from the service environment. It is only needed on an empty database and does not reset the existing admin password on restart. Change future passwords through Moodle.
8. Add Workload planner to the default Dashboard, create test accounts/courses, and repeat the local acceptance tests online. Do not describe Render performance or concurrency as tested until you measure them there.

The license flag in startup accepts Moodle's GPL during installation. The database must be empty on the first run. A partial/unknown database is not wiped; startup stops so it can be inspected. Autodeploy is disabled. On later manual deployments, startup applies Moodle/plugin database upgrades before opening the web server.

## Persistence, scheduling and updates

PostgreSQL holds courses, enrolments, user accounts and planning records. `/var/moodledata` holds uploaded files, Moodle sessions and caches on the persistent disk. Moodle source/plugins are read-only in the built image; make changes through the repository and rebuild. Uploading plugins through the Moodle interface is disabled to avoid losing code on redeploy. The readiness endpoint checks database connectivity and plugin installation, without returning credentials.

Moodle cron runs every minute after the prior run finishes, alongside Apache under Supervisor. It shares the service's mounted disk. A separate Render cron service cannot mount that same disk. Our personal reminders remain in-planner reminders; this setup does not turn them into email/push notifications. Outgoing email has not been configured.

Back up BOTH the database and moodledata before every code upgrade. Restore them as a matching pair. Persistent disks prevent routine restart data loss but are not a complete backup plan. Single-disk deployments have restart/deployment downtime and are not horizontally scalable. Do not delete the database or disk during troubleshooting.

## Existing local test site

Deploying this Blueprint starts a NEW site. It does not migrate the Windows Docker database, courses, accounts, uploads or saved plans. Keep localhost intact. To migrate later, export the database and copy moodledata, restore both to the matching Moodle/plugin version, update the canonical URL and perform Moodle's documented URL migration procedure. Never commit database dumps, uploads or passwords. Validate the restored site before switching users to it.

## Deployment checks

`Render deployment checks` builds the image, installs Moodle against disposable PostgreSQL, checks readiness and the login page, initializes Moodle PHPUnit, runs plugin integration tests, and tests restart. PHP configuration tests reject missing credentials, invalid database ports and non-HTTPS URLs unless the explicit CI-only HTTP override is set. These checks do not provision Render, validate billing, or replace a live Render acceptance test.

If initial deployment fails, inspect logs before retrying. An incomplete install can leave tables; preserve them for diagnosis and only recreate a disposable empty database intentionally. If a later upgrade fails, restore the matching backup or diagnose the migration instead of blindly reverting code against an upgraded database.

## Official references

- https://render.com/docs/docker
- https://render.com/docs/blueprint-spec
- https://render.com/docs/disks
- https://render.com/docs/free
- https://docs.moodle.org/405/en/Moodle_migration
- https://docs.moodle.org/405/en/Cron
