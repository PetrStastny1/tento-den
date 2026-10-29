<?php

/**
 * Name variants from data only: a redirect to the article of another name, the same Wikidata item,
 * or P460/P1560 between the items. And the introduction of an article word for word.
 */

declare(strict_types=1);

use App\Core\Http\HttpResponse;
use App\Model\Import\ArticleSummary;
use App\Model\NameDays\Country;
use App\Model\NameDays\NameInfo;
use App\Model\NameDays\NameVariants;
use App\Model\Wikipedia\WikipediaLeadSource;
use Nette\Utils\FileSystem;
use Tester\Assert;
use Tests\Support\FakeClock;
use Tests\Support\FakeHttpClient;

require __DIR__ . '/../bootstrap.php';


/**
 * @param list<string> $related
 */
function nameInfo(string $name, ?string $item, ?string $articleTitle, array $related = []): NameInfo
{
	$meaning = $articleTitle === null ? null : new ArticleSummary($articleTitle, "$articleTitle je jméno.", 1, new DateTimeImmutable);
	return new NameInfo($name, Country::Czech, $item, $meaning, null, new DateTimeImmutable, $related);
}


test('a) the article redirects to the article of another name of the day: Sylva → Silvie', function () {
	$infos = ['Silvie' => nameInfo('Silvie', 'Q1', 'Silvie'), 'Silvána' => nameInfo('Silvána', null, null), 'Sylva' => nameInfo('Sylva', 'Q1', 'Silvie')];
	Assert::same(['Sylva' => ['name' => 'Silvie', 'rule' => 'a']], NameVariants::find(['Silvie', 'Silvána', 'Sylva'], $infos));
});


test('b) the same Wikidata item, c) P460 or P1560 between the items', function () {
	$same = ['Jiří' => nameInfo('Jiří', 'Q7', null), 'Jura' => nameInfo('Jura', 'Q7', null)];
	Assert::same(['Jura' => ['name' => 'Jiří', 'rule' => 'b']], NameVariants::find(['Jiří', 'Jura'], $same));
	$gender = ['Horymír' => nameInfo('Horymír', 'Q10', 'Horymír', ['Q11']), 'Horymíra' => nameInfo('Horymíra', 'Q11', 'Horymíra (jméno)')];
	Assert::same(['Horymíra' => ['name' => 'Horymír', 'rule' => 'c']], NameVariants::find(['Horymír', 'Horymíra'], $gender));
	$sameAs = ['Klára' => nameInfo('Klára', 'Q20', 'Klára'), 'Kiara' => nameInfo('Kiara', 'Q21', 'Kiara (jméno)', ['Q20'])];
	Assert::same(['Kiara' => ['name' => 'Klára', 'rule' => 'c']], NameVariants::find(['Klára', 'Kiara'], $sameAs));
});


test('names without such data stand alone: Petr and Pavel, similar letters do not count', function () {
	$infos = ['Petr' => nameInfo('Petr', 'Q30', 'Petr'), 'Pavel' => nameInfo('Pavel', 'Q31', 'Pavel'), 'Petra' => nameInfo('Petra', 'Q32', 'Petra')];
	Assert::same([], NameVariants::find(['Petr', 'Pavel', 'Petra'], $infos));
	Assert::same([], NameVariants::find(['Kiara'], ['Kiara' => nameInfo('Kiara', null, null)]));
	Assert::same('Jozef', NameVariants::nameOfArticle('Jozef (prvé meno)'));
});


test('the introduction word for word: parentheses kept, references left out, no missing full stop', function () {
	$klara = WikipediaLeadSource::lead(FileSystem::read(__DIR__ . '/../fixtures/wikimedia/name-days/article-sk-Klára.html'));
	Assert::same('Klára je ženské krstné meno. Pôvod má v latinskom slove clara („jasná“, „svetlá“, ale aj „slávna“). Patrónkou tohto mena je svätá Klára (nar. 1194 v Assisi). Meniny na Slovensku má 29. októbra.', $klara);
	$silvie = (string) WikipediaLeadSource::lead(FileSystem::read(__DIR__ . '/../fixtures/wikimedia/name-days/article-cs-Silvie.html'));
	Assert::match('Silvie (také Sylvie, Silvia, Silva nebo Sylva) je ženské křestní jméno latinského původu%A%', $silvie);
	Assert::null(WikipediaLeadSource::lead('<html><body><table><tr><td><p>Jen infobox.</p></td></tr></table><h2>Dějiny</h2><p>Až po nadpisu.</p></body></html>'));
});


test('the source keeps the revision of the page and the title it was asked for', function () {
	$http = new FakeHttpClient(new HttpResponse(200, ['Content-Revision-Id' => '8400261'], FileSystem::read(__DIR__ . '/../fixtures/wikimedia/name-days/article-sk-Klára.html')), new HttpResponse(404, [], ''));
	$source = new WikipediaLeadSource($http, new FakeClock('2026-10-29 14:00:00'), 'sk');
	$summary = $source->fetch('Klára');
	Assert::same(['Klára', 8400261], [$summary?->title, $summary?->revisionId]);
	Assert::same('https://sk.wikipedia.org/api/rest_v1/page/html/Kl%C3%A1ra?redirect=false', $http->requests[0]);
	Assert::null($source->fetch('Neexistuje'));
});
