<?php

declare(strict_types=1);

namespace App\Model\Events;


/**
 * Normalization, fingerprint and similarity of event texts (pure functions).
 */
final class EventText
{
	public static function normalize(string $text): string
	{
		$text = str_replace("\u{00A0}", ' ', mb_strtolower($text));
		return trim((string) preg_replace('~\s+~u', ' ', $text));
	}


	/**
	 * Fingerprint for exact matching; ignores case and whitespace differences.
	 */
	public static function hash(string $text): string
	{
		return hash('sha256', self::normalize($text));
	}


	/**
	 * 0..1; the higher of character similarity (typos, small edits) and word overlap
	 * (reordered sentences). Thresholds are derived from real edits, see docs/rozhodnuti.md.
	 */
	public static function similarity(string $a, string $b): float
	{
		$a = self::normalize($a);
		$b = self::normalize($b);
		if ($a === $b) {
			return 1.0;
		}

		similar_text($a, $b, $percent);
		return max($percent / 100, self::wordOverlap($a, $b));
	}


	private static function wordOverlap(string $a, string $b): float
	{
		$wordsA = array_unique(preg_split('~[^\p{L}\p{N}]+~u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: []);
		$wordsB = array_unique(preg_split('~[^\p{L}\p{N}]+~u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: []);
		$union = count(array_unique([...$wordsA, ...$wordsB]));
		return $union === 0 ? 0.0 : count(array_intersect($wordsA, $wordsB)) / $union;
	}
}
