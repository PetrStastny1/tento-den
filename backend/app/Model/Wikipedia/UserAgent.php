<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Config\AppConfig;


/**
 * User-Agent required by the Wikimedia User-Agent policy: client/version (contact) library/version.
 */
final class UserAgent
{
	/**
	 * @return non-empty-string
	 */
	public static function create(AppConfig $config): string
	{
		return sprintf('TentoDen/0.1 (%s) PHP/%d.%d', $config->wikimediaContact, PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
	}
}
