<?php

declare(strict_types=1);

namespace App\Model\Events;


enum EventStatus: string
{
	case Active = 'active';
	/** no longer on the day page; kept because favorites may point to it */
	case Missing = 'missing';
	/** the import could not decide whether this is an edit of another event */
	case NeedsReview = 'needs_review';
}
