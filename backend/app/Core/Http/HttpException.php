<?php

declare(strict_types=1);

namespace App\Core\Http;


/**
 * No usable response: network failure, timeout, or retries exhausted.
 */
final class HttpException extends \RuntimeException
{
}
