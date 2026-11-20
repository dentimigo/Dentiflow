#!/bin/sh

# Railway provides the port to which its health check and public proxy connect.
# Apache defaults to port 80, so adapt both its listener and virtual host at boot.
: "${PORT:=8080}"

# PHP runs through mod_php, which requires Apache's prefork MPM. Ensure a
# cached or customized Railway image never starts a second, incompatible MPM.
a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
