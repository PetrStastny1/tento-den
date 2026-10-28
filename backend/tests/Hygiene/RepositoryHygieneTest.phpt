<?php

/**
 * What would end up in git must contain no secrets. Runs in a one-off container with the
 * repository mounted read-only (make test), so the running app never sees the root .env.
 */

declare(strict_types=1);

use Tester\Assert;
use Tester\Environment;

require __DIR__ . '/../bootstrap.php';

$root = getenv('REPO_ROOT');
if ($root === false || $root === '') {
	Environment::skip('REPO_ROOT is not set – run via make test');
}


/**
 * Tracked and untracked files that are not ignored = everything a commit could contain.
 * @return list<string>
 */
function repoFiles(string $root): array
{
	exec(sprintf('git -c safe.directory=%1$s -C %1$s ls-files --cached --others --exclude-standard', escapeshellarg($root)), $files, $exitCode);
	Assert::same(0, $exitCode, 'git ls-files failed');
	return array_values(array_filter($files, fn(string $file): bool => is_file("$root/$file")));
}


$files = repoFiles($root);


test('the repository contains files at all (guards against a broken git call)', function () use ($files) {
	Assert::true(count($files) > 50);
	Assert::contains('backend/app/Bootstrap.php', $files);
});


test('no .env file is committed except .env.example', function () use ($files) {
	Assert::same(['.env.example'], array_values(array_filter($files, fn(string $f): bool => (bool) preg_match('~(^|/)\.env($|\.)~', $f))));
});


test('.env.example has no secret values filled in', function () use ($root) {
	$example = (string) file_get_contents("$root/.env.example");
	foreach (['DB_PASSWORD', 'DB_ROOT_PASSWORD', 'WIKIMEDIA_CONTACT'] as $name) {
		Assert::match("%A%\n$name=\n%A%", $example, $name);
	}
});


test('no default credentials, private keys or API keys', function () use ($root, $files) {
	$patterns = [
		'default password' => '~\b(admin123|change_?me|password123|root/root|admin/admin)\b~i',
		'private key' => '~-----BEGIN [A-Z ]*PRIVATE KEY-----~',
		'Anthropic API key' => '~sk-ant-[a-zA-Z0-9_-]{20,}~',
		'assigned secret' => '~^[ \t]*(DB_PASSWORD|DB_ROOT_PASSWORD|MARIADB_ROOT_PASSWORD|ANTHROPIC_API_KEY)[ \t]*[=:][ \t]*[^\s$]{8,}~m',
	];
	$offenders = [];
	foreach ($files as $file) {
		if (str_ends_with($file, 'RepositoryHygieneTest.phpt') || preg_match('~\.(lock|png|ico|svg)$|package-lock\.json$~', $file)) {
			continue;
		}
		$content = (string) file_get_contents("$root/$file");
		foreach ($patterns as $name => $pattern) {
			if (preg_match($pattern, $content)) {
				$offenders[] = "$file: $name";
			}
		}
	}
	Assert::same([], $offenders);
});


test('runtime folders are ignored but source folders named like them are not', function () use ($files) {
	Assert::same([], array_values(array_filter($files, fn(string $f): bool => (bool) preg_match('~^backend/(log|temp)/(?!\.gitignore$)~', $f))));
	Assert::contains('backend/log/.gitignore', $files);
});
