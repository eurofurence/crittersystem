<?php

$SENTRY_DSN = env('APP_SENTRY_DSN', null);

if ($SENTRY_DSN) {
    \Sentry\init([
        'dsn' => (string) $SENTRY_DSN,
    ]);
}
