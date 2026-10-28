<?php

declare(strict_types=1);

namespace App\Model\Import;


/**
 * The import of a day failed; stored events of that day were left untouched.
 */
final class ImportFailedException extends \RuntimeException
{
}
