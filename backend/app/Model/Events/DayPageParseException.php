<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * The "Události" section contains something the parser does not understand.
 * The whole day must fail – skipping an item would later mark a stored event as missing.
 */
final class DayPageParseException extends \RuntimeException
{
}
