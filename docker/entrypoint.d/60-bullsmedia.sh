#!/bin/sh
set -eu

if [ "${BULLSMEDIA_BOOTSTRAP:-false}" != true ] || [ "${SERVERSIDEUP_DEFAULT_COMMAND:-false}" != true ]; then
    exit 0
fi

cd /var/www/html
printf '%s\n' 'Waiting for MySQL...'
php -r '
$connected = false;
for ($attempt = 0; $attempt < 60; $attempt++) {
    try {
        new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
        $connected = true;
        break;
    } catch (PDOException $exception) {
        sleep(2);
    }
}
if (! $connected) {
    fwrite(STDERR, "MySQL did not become ready.\n");
    exit(1);
}
'

if [ ! -f .env ]; then
    cp .env.example docker/runtime/.env
    chmod 600 docker/runtime/.env
fi

php artisan config:clear --no-interaction
php -r '
require "vendor/autoload.php";
$environment = Dotenv\Dotenv::parse(file_get_contents(".env"));
exit(empty($environment["APP_KEY"]) ? 0 : 1);
' && php artisan key:generate --force --no-interaction

mkdir -p storage/app/google
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan tinker --execute='App\Models\User::firstOrCreate(["email" => getenv("ADMIN_EMAIL") ?: "admin@bullsmedia.test"], ["name" => "BullsMedia Admin", "password" => getenv("ADMIN_PASSWORD") ?: "password"]);' --no-interaction
php artisan storage:link --force --no-interaction
php artisan filament:optimize --no-interaction
printf '%s\n' 'BullsMedia bootstrap complete.'