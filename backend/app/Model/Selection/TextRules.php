<?php

declare(strict_types=1);

namespace App\Model\Selection;


/**
 * A readable list of named regular expressions (config/selection.neon) matched against event texts.
 */
final readonly class TextRules
{
	/** @var array<string, string> name => pattern */
	private array $rules;


	/**
	 * @param array<string, string> $rules name => PCRE pattern with delimiters
	 */
	public function __construct(array $rules)
	{
		foreach ($rules as $name => $pattern) {
			if (@preg_match($pattern, '') === false) {
				throw new \InvalidArgumentException("Invalid pattern of rule '$name': $pattern");
			}
		}
		$this->rules = $rules;
	}


	/**
	 * Name of the first matching rule, or null.
	 */
	public function match(string $text): ?string
	{
		foreach ($this->rules as $name => $pattern) {
			if (preg_match($pattern, $text) === 1) {
				return $name;
			}
		}
		return null;
	}
}
