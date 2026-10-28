<?php

declare(strict_types=1);

namespace App\Model\Events;


enum ReviewRole: string
{
	/** a stored event that may have been edited, removed or vandalized */
	case Original = 'original';
	/** an imported event that may replace the originals of its group */
	case Replacement = 'replacement';
}
