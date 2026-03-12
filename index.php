<?php
// Responsibility: Front controller – bootstraps the app and dispatches every
// incoming request through the router to the correct cached PHP file.

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/router.php';

route_request();
