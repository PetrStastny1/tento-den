<?php

/**
 * Which linked page becomes the primary article of an event.
 */

declare(strict_types=1);

use App\Model\Events\ArticleInfo;
use App\Model\Events\LinkFilter;
use App\Model\Events\PrimaryArticleSelector;
use Tester\Assert;
use Tests\Support\TestContainer;

require __DIR__ . '/../bootstrap.php';


function article(string $title): ArticleInfo
{
	return new ArticleInfo(crc32($title), $title, 1, null);
}


test('years, dates and other non-article pages are never candidates', function () {
	$filter = new LinkFilter;
	foreach (['1918', '45 př. n. l.', '28. říjen', '1984 v dopravě', '20. století', '60. léta 20. století', 'Soubor:Mapa.png', 'Kategorie:Praha'] as $title) {
		Assert::false($filter->isArticleCandidate($title), $title);
	}
	foreach (['Vznik Československa', 'Socha Svobody', '1. pluk', 'Star Wars: Epizoda IV'] as $title) {
		Assert::true($filter->isArticleCandidate($title), $title);
	}
});


test('the first article that is not generic is primary', function () {
	$selector = new PrimaryArticleSelector(['Praha', 'Spojené státy americké']);
	$primary = $selector->select([article('Praha'), article('Vznik Československa'), article('První republika')]);
	Assert::same('Vznik Československa', $primary?->title);
});


test('an event linking only generic articles has no primary article', function () {
	$selector = new PrimaryArticleSelector(['Praha', 'Spojené státy americké']);
	Assert::null($selector->select([article('Praha'), article('Spojené státy americké')]));
	Assert::null($selector->select([]));
});


test('the configured list of generic articles is used by the container', function () {
	$selector = TestContainer::create()->getByType(PrimaryArticleSelector::class);
	foreach (['Praha', 'Spojené státy americké', 'Druhá světová válka', 'Československo'] as $title) {
		Assert::true($selector->isGeneric($title), $title);
	}
	Assert::false($selector->isGeneric('Vznik Československa'));
});
