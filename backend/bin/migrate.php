<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database\Migrations;
use Nextras\Migrations\Printers\Console;

try {
	$container = (new App\Bootstrap(getenv()))->createContainer();
	$container->getByType(Migrations::class)->migrate(new Console);
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
