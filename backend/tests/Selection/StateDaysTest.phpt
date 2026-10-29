<?php

/**
 * State holidays and significant days (Act No. 245/2000 Coll.): Easter, the calendar from the
 * configuration and commemorated events in the selection (pure logic).
 */

declare(strict_types=1);

use App\Model\Events\MonthDay;
use App\Model\Events\Section;
use App\Model\Selection\Candidate;
use App\Model\Selection\DaySelector;
use App\Model\StateDays\Easter;
use App\Model\StateDays\StateDayCalendar;
use App\Model\StateDays\StateDayType;
use Nette\Neon\Neon;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Easter Sunday for several years, Good Friday and Easter Monday around it', function () {
	$expected = [2000 => '04-23', 2019 => '04-21', 2024 => '03-31', 2025 => '04-20', 2026 => '04-05', 2027 => '03-28', 2038 => '04-25', 2285 => '03-22'];
	foreach ($expected as $year => $date) {
		Assert::same("$year-$date", Easter::sunday($year)->format('Y-m-d'));
	}

	$calendar = new StateDayCalendar([
		['easter' => -2, 'name' => 'Velký pátek', 'type' => 'other', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p2', 'article' => 'Velký pátek'],
		['easter' => 1, 'name' => 'Velikonoční pondělí', 'type' => 'other', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p2', 'article' => 'Velikonoční pondělí'],
	]);
	[$friday, $monday] = $calendar->all();
	Assert::same(['04-03', '04-06'], [$friday->dateIn(2026)->format(), $monday->dateIn(2026)->format()]);
	Assert::same(['03-29', '04-01'], [$friday->dateIn(2024)->format(), $monday->dateIn(2024)->format()]);
	Assert::same(['04-18', '04-21'], [$friday->dateIn(2025)->format(), $monday->dateIn(2025)->format()]);
});


test('movable holidays only on their date in a known year; without a year they are left out', function () {
	$calendar = new StateDayCalendar([
		['easter' => 1, 'name' => 'Velikonoční pondělí', 'type' => 'other', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p2', 'article' => 'Velikonoční pondělí'],
		['date' => '05-01', 'name' => 'Svátek práce', 'type' => 'other', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p2', 'article' => 'Svátek práce'],
	]);
	$names = fn(string $day, ?int $year): array => array_map(fn($d) => $d->name, $calendar->forDay(MonthDay::fromString($day), $year));
	Assert::same(['Velikonoční pondělí'], $names('04-06', 2026));
	Assert::same([], $names('04-06', 2027));
	Assert::same([], $names('04-06', null));
	Assert::same(['Svátek práce'], $names('05-01', null));
});


test('the configuration lists all days of the act: 7 state holidays, 7 other holidays, 20 significant days', function () {
	$config = Neon::decodeFile(__DIR__ . '/../../config/state-days.neon');
	$calendar = new StateDayCalendar($config['parameters']['stateDays']);
	$byType = array_count_values(array_map(fn($d) => $d->type->value, $calendar->all()));
	Assert::same(['state' => 7, 'other' => 7, 'significant' => 20], $byType);
	Assert::same('Státní svátek', StateDayType::State->label());
	Assert::same('den-ceske-statnosti', $calendar->forDay(new MonthDay(9, 28), null)[0]->id);
	Assert::same(['Den obnovy samostatného českého státu', 'Nový rok'], array_map(fn($d) => $d->name, $calendar->forDay(new MonthDay(1, 1), null)));
});


test('an invalid configuration is refused', function () {
	$base = ['date' => '10-28', 'name' => 'X', 'type' => 'state', 'source' => 'https://www.zakonyprolidi.cz/cs/2000-245#p1'];
	Assert::exception(fn() => new StateDayCalendar([$base]), InvalidArgumentException::class, '%a%exactly one of event (year and text) and article%a%');
	Assert::exception(fn() => new StateDayCalendar([$base + ['article' => 'A', 'event' => ['year' => 1918, 'text' => 'vyhlášen stát']]]), InvalidArgumentException::class, '%a%exactly one of event%a%');
	Assert::exception(fn() => new StateDayCalendar([['easter' => 1, 'event' => ['year' => 33, 'text' => 'vzkříšení Ježíše']] + $base]), InvalidArgumentException::class, '%a%exactly one of date and easter%a%');
	Assert::exception(fn() => new StateDayCalendar([['type' => 'holiday', 'article' => 'A'] + $base]), InvalidArgumentException::class, '%a%type%a%');
	Assert::exception(fn() => new StateDayCalendar([['source' => 'https://example.org/', 'article' => 'A'] + $base]), InvalidArgumentException::class, '%a%source%a%');
	Assert::exception(fn() => new StateDayCalendar([$base + ['article' => 'A'], $base + ['article' => 'B']]), InvalidArgumentException::class, '%a%duplicate names');
});


test('a commemorated event is always in the ten and first, even with a low score, and takes no Czech place', function () {
	$candidates = [];
	foreach (range(1, 12) as $id) {
		// Czech candidates have the lowest scores: only the guaranteed three get in
		$candidates[] = new Candidate($id, $id >= 9 ? Section::Czech : Section::World, 1900 + $id, 1 - $id / 20, null);
	}
	$candidates[] = new Candidate(99, Section::World, 1918, 0.05, null); // the weakest of all

	$chosen = (new DaySelector)->select($candidates, [99]);
	Assert::count(10, $chosen);
	Assert::same(99, $chosen[0]->id);
	Assert::same(3, count(array_filter($chosen, fn(Candidate $c) => $c->section === Section::Czech)));
	$rest = array_map(fn(Candidate $c) => $c->year, array_slice($chosen, 1));
	$sorted = $rest;
	rsort($sorted);
	Assert::same($sorted, $rest);
});
