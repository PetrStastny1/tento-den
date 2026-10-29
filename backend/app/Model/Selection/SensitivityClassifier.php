<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * Marks cards about victims, wars, disasters or terror. Only a property of the card
 * (calmer design, no jokes later) – it never influences the selection.
 */
final class SensitivityClassifier
{
	public function __construct(
		private readonly TextRules $rules,
	) {
	}


	public function isSensitive(string $text): bool
	{
		return $this->rules->match($text) !== null;
	}
}
