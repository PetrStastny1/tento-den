<?php

declare(strict_types=1);

namespace App\Api;

use Nette\Application\BadRequestException;
use Nette\Http\IResponse;


/**
 * A client error whose message is safe to show to the client (no internal details).
 */
final class ApiException extends BadRequestException
{
	public static function invalidRequest(string $message): self
	{
		return new self($message, IResponse::S400_BadRequest);
	}


	public static function notFound(string $message): self
	{
		return new self($message, IResponse::S404_NotFound);
	}


	public static function methodNotAllowed(): self
	{
		return new self('Povolena je jen metoda GET.', IResponse::S405_MethodNotAllowed);
	}
}
