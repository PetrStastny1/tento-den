<?php

/**
 * Round anniversaries: years since the event (BC included, no year 0) and the round steps.
 */

declare(strict_types=1);

use App\Model\Anniversaries\RoundAnniversary;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('years since the event, before Christ without a year 0', function () {
	Assert::same(108, RoundAnniversary::yearsSince(1918, 2026));
	Assert::same(2070, RoundAnniversary::yearsSince(-45, 2026)); // 45 BC → AD 2026
	Assert::same(1, RoundAnniversary::yearsSince(-1, 1)); // 1 BC → AD 1
	Assert::same(0, RoundAnniversary::yearsSince(2026, 2026));
	Assert::exception(fn() => RoundAnniversary::yearsSince(0, 2026), InvalidArgumentException::class);
});


test('round: 25, 50, 75, 100, 150, 200, 250, 300, then multiples of 100', function () {
	$round = [];
	foreach (range(1, 1200) as $years) {
		if (RoundAnniversary::of(2026 - $years, 2026) !== null) {
			$round[] = $years;
		}
	}
	Assert::same([25, 50, 75, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200], $round);
	Assert::null(RoundAnniversary::of(2026 - 350, 2026)); // 350 is not a step
	Assert::null(RoundAnniversary::of(2026 - 125, 2026));
	Assert::same(2100, RoundAnniversary::of(-75, 2026)); // 75 BC
	Assert::null(RoundAnniversary::of(-74, 2026));
	Assert::same('250 let', RoundAnniversary::label(250));
});
