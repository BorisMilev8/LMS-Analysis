#!/bin/sh
set -eu
case "${PORT:-10000}" in
  ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1 ;;
esac
[ "${PORT:-10000}" -ge 1 ] && [ "${PORT:-10000}" -le 65535 ]
mkdir -p /var/moodledata
chown www-data:www-data /var/moodledata
chmod 0770 /var/moodledata
# Moodle startup completes before the public HTTP server accepts requests.
runuser -u www-data -- php /opt/moodle-render/bootstrap.php
sed -i "s/^Listen 80$/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/moodle.conf
