#!/bin/sh
# Runs beside Apache so it sees the same moodledata disk. Supervisor handles signals.
while true; do
    php /var/www/html/admin/cli/cron.php || echo 'Moodle cron failed; retrying on next cycle' >&2
    sleep 60
done
