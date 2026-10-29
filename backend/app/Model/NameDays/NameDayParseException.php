<?php

declare(strict_types=1);

namespace App\Model\NameDays;


/**
 * The "Svátky" section has something that looks like names but cannot be read for sure, or the
 * Czech list cannot be found unambiguously. The whole day fails, like for events.
 */
final class NameDayParseException extends \RuntimeException
{
}
