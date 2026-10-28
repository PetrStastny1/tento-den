<?php

/**
 * Creates .env from .env.example and fills every empty secret with a random value.
 * Never prints the values, never overwrites an existing .env, file mode 0600.
 * Usage: php bin/init-env.php [target-dir]   (make init-env runs it in Docker)
 */

declare(strict_types=1);

const SecretKeys = ['DB_PASSWORD', 'DB_ROOT_PASSWORD'];

$dir = rtrim($argv[1] ?? dirname(__DIR__), '/');
$examplePath = "$dir/.env.example";
$envPath = "$dir/.env";

if (file_exists($envPath)) {
	fwrite(STDERR, ".env už existuje – nepřepisuji ho.\n");
	exit(1);
}
$example = file_get_contents($examplePath);
if ($example === false) {
	fwrite(STDERR, "Nelze přečíst $examplePath\n");
	exit(1);
}

$generated = [];
$content = preg_replace_callback(
	'~^(' . implode('|', SecretKeys) . ')=$~m',
	function (array $m) use (&$generated): string {
		$generated[] = $m[1];
		return $m[1] . '=' . bin2hex(random_bytes(24)); // 48 hex chars: safe in shell, URL and SQL
	},
	$example,
);

// create with 0600 from the start, so the secrets are never readable by others
$oldUmask = umask(0077);
$written = file_put_contents($envPath, $content, LOCK_EX);
umask($oldUmask);
if ($written === false) {
	fwrite(STDERR, "Nelze zapsat $envPath\n");
	exit(1);
}
chmod($envPath, 0600);

echo '.env vytvořen, vygenerováno: ' . implode(', ', $generated) . "\n";
if (preg_match('~^WIKIMEDIA_CONTACT=$~m', (string) $content)) {
	echo "Doplňte do .env WIKIMEDIA_CONTACT (kontaktní e-mail pro Wikimedia API).\n";
}
