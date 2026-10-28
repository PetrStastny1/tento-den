<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

try {
	$container = (new App\Bootstrap(getenv()))->bootWebApplication();
} catch (App\Core\Config\InvalidConfigurationException | App\Core\Database\PendingMigrationsException $e) {
	// the reason goes to the server log, the client only learns that the API is not available
	error_log($e->getMessage());
	http_response_code(503);
	header('Content-Type: application/json; charset=utf-8');
	echo '{"error":{"code":503,"message":"Service Unavailable"}}';
	exit;
}

$container->getByType(Nette\Application\Application::class)->run();
