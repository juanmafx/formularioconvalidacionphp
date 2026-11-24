<?php

declare(strict_types=1);

use ContactForm\Application;
use ContactForm\Config\AppConfig;
use ContactForm\Http\Request;

ini_set('display_errors', '0');

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $assetRoot = realpath(__DIR__ . '/assets');
    $resolvedPath = is_string($requestedPath) ? realpath(__DIR__ . $requestedPath) : false;
    if ($assetRoot !== false && $resolvedPath !== false && str_starts_with($resolvedPath, $assetRoot . '/') && is_file($resolvedPath)) {
        return false;
    }
}

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

try {
    $application = new Application(AppConfig::fromEnvironment(getenv(), $projectRoot), $projectRoot);
} catch (Throwable $exception) {
    error_log('Application bootstrap failed: ' . $exception::class);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Service unavailable.';

    return;
}

$application->kernel->handle(Request::fromGlobals())->emit();
