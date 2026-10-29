<?php

/**
 * GET /api/health against the test database.
 */

declare(strict_types=1);

use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\Database\ConnectionException;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tester\Assert;
use Tests\Support\MemoryLogger;
use Tests\Support\TestContainer;
use Tracy\ILogger;

require __DIR__ . '/../bootstrap.php';

TestContainer::lockDatabase(); // other tests empty the shared test database in parallel


/**
 * @param array<string, string> $env
 * @return array{int, string}
 */
function requestHealth(array $env, MemoryLogger $logger, string $method = 'GET'): array
{
	$container = TestContainer::create($env);
	TestContainer::replace($container, ILogger::class, $logger);

	$presenter = $container->getByType(IPresenterFactory::class)->createPresenter('Api:Health');
	$response = $presenter->run(new Request('Api:Health', $method));
	Assert::type(JsonResponse::class, $response);
	assert($response instanceof JsonResponse);

	return [
		$container->getByType(IResponse::class)->getCode(),
		Json::encode($response->getPayload()),
	];
}


test('returns 200 and status ok when the database is reachable', function () {
	$logger = new MemoryLogger;
	[$code, $body] = requestHealth([], $logger);

	Assert::same(200, $code);
	Assert::same('{"status":"ok"}', $body);
	Assert::same([], $logger->entries);
});


test('returns 503 without details and logs the reason when the database is unreachable', function () {
	$logger = new MemoryLogger;
	[$code, $body] = requestHealth(['DB_PASSWORD' => 'wrong-password'], $logger);

	Assert::same(503, $code);
	Assert::same('{"status":"error"}', $body);
	Assert::count(1, $logger->entries);
	Assert::type(ConnectionException::class, $logger->entries[0][0]);
});


test('rejects other methods than GET with 405', function () {
	$e = Assert::exception(
		fn() => requestHealth([], new MemoryLogger, 'POST'),
		BadRequestException::class,
	);
	assert($e instanceof BadRequestException);
	Assert::same(405, $e->getHttpCode());
});
