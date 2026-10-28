<?php

/**
 * Validation of environment variables before the container is built.
 */

declare(strict_types=1);

use App\Core\Config\AppConfig;
use App\Core\Config\EnvironmentConfigLoader;
use App\Core\Config\InvalidConfigurationException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


const ValidEnv = [
	'APP_ENV' => 'production',
	'DB_HOST' => 'mariadb',
	'DB_PORT' => '3306',
	'DB_NAME' => 'tento_den',
	'DB_USER' => 'tento_den',
	'DB_PASSWORD' => 'secret',
	'WIKIMEDIA_CONTACT' => 'kontakt@example.org',
	'PATH' => '/usr/bin', // unrelated variables are ignored
];


test('a valid environment gives typed configuration', function () {
	$config = (new EnvironmentConfigLoader)->load(ValidEnv);
	Assert::type(AppConfig::class, $config);
	Assert::true($config->isProduction());
	Assert::false($config->isDebugMode());
	Assert::same(3306, $config->database->port);
	Assert::same('mysql:host=mariadb;port=3306;dbname=tento_den;charset=utf8mb4', $config->database->dsn());
});


test('missing, empty or malformed values stop the start with a readable list', function () {
	$cases = [
		'WIKIMEDIA_CONTACT' => [['WIKIMEDIA_CONTACT' => null], "The mandatory item 'WIKIMEDIA_CONTACT' is missing."],
		'contact not an e-mail' => [['WIKIMEDIA_CONTACT' => 'Petr'], "The item 'WIKIMEDIA_CONTACT' expects to be email, 'Petr' given."],
		'empty password' => [['DB_PASSWORD' => ''], "The length of item 'DB_PASSWORD' expects to be in range 1.., 0 bytes given."],
		'unknown environment' => [['APP_ENV' => 'staging'], "The item 'APP_ENV' expects to be 'development'|'production', 'staging' given."],
		'port' => [['DB_PORT' => 'abc'], "The item 'DB_PORT' expects to match pattern '\\d{1,5}', 'abc' given."],
	];
	foreach ($cases as $case => [$override, $message]) {
		$env = array_filter(array_merge(ValidEnv, $override), fn($value) => $value !== null);
		$e = Assert::exception(fn() => (new EnvironmentConfigLoader)->load($env), InvalidConfigurationException::class);
		assert($e !== null);
		Assert::contains('Neplatná konfigurace prostředí (.env)', $e->getMessage(), $case);
		Assert::contains($message, $e->getMessage(), $case);
	}
});


test('all problems are reported at once', function () {
	$e = Assert::exception(fn() => (new EnvironmentConfigLoader)->load(['APP_ENV' => 'development']), InvalidConfigurationException::class);
	assert($e !== null);
	foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WIKIMEDIA_CONTACT'] as $name) {
		Assert::contains("'$name'", $e->getMessage());
	}
});
