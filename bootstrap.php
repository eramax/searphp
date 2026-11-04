<?php

declare(strict_types=1);

require __DIR__ . '/src/Autoload.php';

use SearPHP\Config;
use SearPHP\Support\Env;

date_default_timezone_set('UTC');
Env::load(__DIR__ . '/.env');

/** @var array<string, mixed> $values */
$values = require __DIR__ . '/config/config.php';

return new Config($values);
