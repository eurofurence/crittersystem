<?php

/**
 * Bootstrap application
 */

use Engelsystem\Application;

//use Engelsystem\Http\UrlGeneratorInterface;

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

/**
 * Migration check - reference files:
 * - config/migration.ok
 * - config/migration.fail
 * - config/migration.running
 */
if (!file_exists(__DIR__ . '/../config/migration.ok')) {

    if (!file_exists(__DIR__ . '/../config/migration.running') &&
        !file_exists(__DIR__ . '/../config/migration.fail')) {
        http_response_code(503);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_missing.html');
        echo $page_content;
        die();

    } elseif (file_exists(__DIR__ . '/../config/migration.running') &&
              file_exists(__DIR__ . '/../config/migration.fail')) {
        // Both files exists at the same time
        http_response_code(500);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/500.html');
        echo $page_content;
        die();

    } elseif (file_exists(__DIR__ . '/../config/migration.running')) {
        http_response_code(200);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_run.html');
        echo $page_content;
        die();

    } elseif (file_exists(__DIR__ . '/../config/migration.fail')) {
        http_response_code(503);
        $page_content = file_get_contents(__DIR__ . '/../resources/views/static/migration_fail.html');
        echo $page_content;
        die();

    }
}
