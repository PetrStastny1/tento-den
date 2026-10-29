<?php

declare(strict_types=1);

namespace App\Model\Import;


/**
 * Data could not be loaded (outage, unexpected response). Never means "no data".
 */
class SourceException extends \RuntimeException
{
}
