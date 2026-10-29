<?php

/**
 * Name days from the "Svátky" section of real day pages (fixtures/wikimedia/name-days, day pages).
 */

declare(strict_types=1);

use App\Model\Events\MonthDay;
use App\Model\NameDays\NameDayParseException;
use App\Model\NameDays\NameDayParser;
use App\Model\NameDays\NameDayRules;
use App\Model\NameDays\ParsedNameDay;
use Nette\Neon\Neon;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * @param list<array{day: string, text: string, reason: string}> $ignoredItems
 */
function nameDayParser(array $ignoredItems = []): NameDayParser
{
	$config = Neon::decodeFile(__DIR__ . '/../../config/name-day-rules.neon')['parameters']['nameDayRules'];
	return new NameDayParser(new NameDayRules($config['czechLabels'], $ignoredItems));
}


/**
 * @return array{cz: list<string>, sk: list<string>} "line:name (note)"
 */
function parsedNames(string $fixture, string $day, ?NameDayParser $parser = null): array
{
	$html = FileSystem::read(__DIR__ . '/../fixtures/wikimedia/' . $fixture);
	$result = ($parser ?? nameDayParser())->parse($html, MonthDay::fromString($day));
	$out = ['cz' => [], 'sk' => []];
	foreach ($result->nameDays as $n) {
		$out[$n->country->value][] = "{$n->line}:{$n->name}" . ($n->note === null ? '' : " ({$n->note})");
	}
	return $out;
}


test('29. 10.: Silvie and its variants are the main entry, Narcis on the next line', function () {
	Assert::same(['1:Silvie', '1:Silvána', '1:Sylva', '2:Narcis', '2:Narcisa'], parsedNames('name-days/svatky-10-29.html', '10-29')['cz']);
});


test('several names on one line, further lines, Slovak name as „Slovensko: …“ under „Svět“', function () {
	Assert::same(['cz' => ['1:Josef', '1:Josefa', '1:Josefína', '2:Sibyla'], 'sk' => ['1:Jozef']], parsedNames('name-days/svatky-03-19.html', '03-19'));
	Assert::same(['cz' => ['1:Radmila', '1:Radomila', '2:Benjamín', '3:Danuta', '4:Jenovéfa', '5:Gaudencia'], 'sk' => ['1:Daniela']], parsedNames('name-days/svatky-01-03.html', '01-03')); // „Slovensko – Daniela“
});


test('heading variants: own Slovak heading, „Svátky (Česko)“, a paragraph „Česko:“, a definition term', function () {
	Assert::same(['1:Jerguš'], parsedNames('name-days/svatky-08-03.html', '08-03')['sk']);
	Assert::same('1:Bronislav', parsedNames('name-days/svatky-09-03.html', '09-03')['cz'][0]);
	Assert::same(['1:Karel', '1:Karla', '2:Jesika', '3:Mojžíš', '4:Skarlet', '4:Skarleta', '4:Šarlota'], parsedNames('name-days/svatky-11-04.html', '11-04')['cz']); // „Karel, též Karla“
	Assert::same(['1:René', '2:Rufus', '3:Tor', '3:Torsten', '4:Tristan'], parsedNames('name-days/svatky-11-28.html', '11-28')['cz']); // „Česká republika“, socialist calendar skipped
	Assert::same(['cz' => ['1:Horymír', '1:Horymíra', '1:Majoš'], 'sk' => ['1:Radomír']], parsedNames('day-page-02-29-full.html', '02-29'));
});


test('variants in parentheses, notes after a dash, names separated by a slash', function () {
	Assert::same(['1:Tadeáš', '2:Arsen', '2:Arzen', '2:Asterius', '2:Asterie', '2:Asta'], array_slice(parsedNames('name-days/svatky-10-30.html', '10-30')['cz'], 0, 6));
	Assert::same('1:Sáva (jméno hebrejského původu, které znamená „děd, stařec“.)', parsedNames('name-days/svatky-11-14.html', '11-14')['cz'][0]);
	Assert::same(['1:Ľuboslav', '1:Ľuboslava'], parsedNames('name-days/svatky-09-20.html', '09-20')['sk']);
});


test('holidays and other items of the Czech list are not names; an empty section has no names', function () {
	$result = nameDayParser()->parse(FileSystem::read(__DIR__ . '/../fixtures/wikimedia/day-page-10-28.html'), MonthDay::fromString('10-28'));
	Assert::same(['Juda', 'Tadeáš', 'Alfréd'], array_map(fn(ParsedNameDay $n) => $n->name, $result->nameDays));
	Assert::contains('cz: „Státní svátek: Den vzniku samostatného československého státu“', $result->skipped);
	Assert::same(['cz' => [], 'sk' => []], parsedNames('name-days/svatky-09-13.html', '09-13'));
});


test('something that looks like names but cannot be read fails the day; so does an ambiguous Czech list', function () {
	$page = fn(string $czech) => "<html><body><section><h2>Svátky</h2><section><h3>Česko</h3><ul><li>$czech</li></ul></section></section></body></html>";
	Assert::exception(fn() => nameDayParser()->parse($page('Josef; Josefa'), new MonthDay(3, 19)), NameDayParseException::class, 'Nečitelný seznam jmen (cz): „Josef; Josefa“');
	Assert::exception(fn() => nameDayParser()->parse($page('Josef, josefa'), new MonthDay(3, 19)), NameDayParseException::class, 'Nečitelný seznam jmen (cz): „Josef, josefa“');
	Assert::exception(
		fn() => nameDayParser()->parse('<html><body><section><h2>Svátky</h2><h3>Česko</h3><ul><li>Josef</li></ul><h3>Česko</h3><ul><li>Sibyla</li></ul></section></body></html>', new MonthDay(3, 19)),
		NameDayParseException::class,
		'Sekce „Svátky“ má 2 seznamy pro Česko',
	);
	Assert::exception(
		fn() => nameDayParser()->parse('<html><body><section><h2>Svátky</h2><h3>Morava</h3><ul><li>Josef</li></ul></section></body></html>', new MonthDay(3, 19)),
		NameDayParseException::class,
		'Sekce „Svátky“ nemá seznam pro Česko (nadpisy: , Morava)',
	);
	Assert::exception(fn() => nameDayParser()->parse('<html><body><p>nic</p></body></html>', new MonthDay(3, 19)), NameDayParseException::class, 'Stránka nemá sekci „Svátky“');
	// an explicit rule (day and exact text) lets a known item through as „not a name“
	Assert::same([], nameDayParser([['day' => '03-19', 'text' => 'Josef; Josefa', 'reason' => 'test']])->parse($page('Josef; Josefa'), new MonthDay(3, 19))->nameDays);
});
