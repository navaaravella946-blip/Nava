
#!/bin/sh
set -eu

port="${PORT:-10000}"

case "$port" in
    ''|*[!0-9]*)
        echo "PORT must contain only digits." >&2
        exit 1
        ;;
esac

echo "Running Laravel database migrations..."
php artisan migrate --force

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache..."
exec apache2-foreground
