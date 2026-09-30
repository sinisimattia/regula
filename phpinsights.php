<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHP Insights entry point
|--------------------------------------------------------------------------
|
| The standalone binary (`vendor/bin/phpinsights`, which is what `composer insights` runs) looks
| for THIS file and only this file. `config/insights.php` is the Laravel package's config and is
| read only by `php artisan insights`.
|
| Before this file existed, `composer insights` silently ran on stock defaults: the curated
| `remove` list and the `add` block in config/insights.php had no effect on it at all.
|
| So the configuration stays in config/insights.php — one copy, reachable from both entry points —
| and this file exists purely to point the binary at it.
|
*/

return require __DIR__ . '/config/insights.php';
