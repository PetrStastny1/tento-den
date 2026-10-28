<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Console\ConsoleApplication;
use App\Core\Console\Output;

$output = new Output(STDOUT, STDERR);
try {
	$container = (new App\Bootstrap(getenv()))->createContainer();
} catch (App\Core\Config\InvalidConfigurationException $e) {
	$output->error($e->getMessage());
	exit(1);
}

exit($container->getByType(ConsoleApplication::class)->run(array_slice($argv, 1), $output));
