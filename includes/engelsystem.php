<?php

/**
 * Bootstrap application
 */

use Engelsystem\Application;

/**
 * Migration check - reference files:
 * - config/migration.ok
 * - config/migration.fail
 * - config/migration.running
 */
$file_migration_ok = __DIR__ . '/../config/migration.ok';
$file_migration_fail = __DIR__ . '/../config/migration.fail';
$file_migration_running = __DIR__ . '/../config/migration.running';

if (!file_exists(filename: $file_migration_ok)) {
    if (
        !file_exists(filename:$file_migration_running) &&
        !file_exists(filename:$file_migration_fail)
    ) {
        http_response_code(503);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_missing.html');
        echo $page_content;
        die();
    } elseif (
        file_exists(filename:$file_migration_running) &&
              file_exists(filename:$file_migration_fail)
    ) {
        // Both files exists at the same time - This means the migration failed during execution of the class
        // or the user pressed CTRL+C during run and clean-up did not happen...
        http_response_code(500);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_fail.html');
        echo $page_content;
        die();
    } elseif (file_exists(filename:$file_migration_running)) {
        http_response_code(200);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_run.html');
        echo $page_content;
        die();
    } elseif (file_exists(filename:$file_migration_fail)) {
        http_response_code(503);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_fail.html');
        echo $page_content;
        die();
    }
}

require __DIR__ . '/application.php';

/**
 * Include legacy code
 */
require __DIR__ . '/includes.php';

/**
 * Check for maintenance
 */
/** @var Application $app */
if ($app->get('config')->get('maintenance')) {
    http_response_code(503);

    // $url = $app->get(UrlGeneratorInterface::class);

    $maintenance = file_get_contents(__DIR__ . '/../resources/views/static/maintenance.html');
    // $maintenance = str_replace('%APP_NAME%', htmlspecialchars($app->get('config')->get('app_name')), $maintenance);
    // $maintenance = str_replace('%ASSETS_PATH%', $url->to(''), $maintenance);

    echo $maintenance;

    die(); //Just end everything...
}
