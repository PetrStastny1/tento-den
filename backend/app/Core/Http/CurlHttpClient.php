<?php

declare(strict_types=1);

namespace App\Core\Http;


final class CurlHttpClient implements HttpClient
{
	/**
	 * @param non-empty-string $userAgent
	 */
	public function __construct(
		private readonly string $userAgent,
		private readonly int $timeoutSeconds = 10,
	) {
	}


	public function get(string $url, array $headers = []): HttpResponse
	{
		$responseHeaders = [];
		$curl = curl_init($url);
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_TIMEOUT => $this->timeoutSeconds,
			CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
			CURLOPT_ENCODING => '', // accept gzip and decode it
			CURLOPT_USERAGENT => $this->userAgent,
			CURLOPT_HTTPHEADER => array_map(
				fn(string $name, string $value): string => "$name: $value",
				array_keys($headers),
				$headers,
			),
			CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) {
					$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
				} elseif (str_starts_with($line, 'HTTP/')) {
					$responseHeaders = []; // headers of a redirect are replaced by the final ones
				}
				return strlen($line);
			},
		]);

		$body = curl_exec($curl);
		if (!is_string($body)) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new HttpException("GET $url failed: $error");
		}

		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);
		return new HttpResponse($status, $responseHeaders, $body);
	}
}
