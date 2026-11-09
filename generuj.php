#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PdfGenerator\Application;

$config = require __DIR__ . '/config.php';

exit((new Application($config))->run($argv));
