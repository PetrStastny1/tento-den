<?php

declare(strict_types=1);

namespace App\Model\Wikipedia;

use App\Core\Http\HttpClient;
use App\Core\Http\HttpException;
use App\Model\Import\SourceException;
use App\Model\NameDays\Bearer;
use App\Model\NameDays\BearerSource;
use App\Model\NameDays\Country;
use Nette\Utils\Json;
use Nette\Utils\JsonException;


/**
 * A known bearer from the Wikidata Query Service: humans with the given name (P735) born (P19) in a
 * place that lies in today's Czechia (CZ) or Slovakia (SK) (P17 of the place), with an article on the
 * Wikipedia of the mode. The place of birth, not the citizenship: Jan Hus, Charles IV or Comenius have
 * the Kingdom of Bohemia as their citizenship, not Czechia or Czechoslovakia.
 * - Never: an occupation (P106, or a subclass) in erotica or pornography; someone convicted (P1399)
 *   who has no occupation in the fields below – known mainly for a crime. A conviction alone does not
 *   exclude: political prisoners (Václav Havel, Milada Horáková) were convicted too.
 * - Only someone whose label starts with the name: „Miloš Forman“ has Jan among his given names and
 *   „Silvie Maryško“ is not a known „Sylva“ (an article redirected to „Silvie“).
 * - First people of history and culture (politics, science, arts, literature, sport, film, music,
 *   rulers, soldiers, resistance), then the most sitelinks.
 * - People excluded after a human review are skipped.
 */
final class WikidataQueryBearerSource implements BearerSource
{
	private const Endpoint = 'https://query.wikidata.org/sparql';
	private const BirthCountries = [
		'cz' => 'Q213', // Czech Republic
		'sk' => 'Q214', // Slovakia
	];
	private const ExcludedOccupations = [
		'Q488111',   // pornographic film actor
		'Q17456089', // pornographic film director
		'Q3286043',  // erotic photography model
		'Q852857',   // sex worker
		'Q14915751', // prostitute
		'Q1027930',  // webcam model
	];
	private const NotableFields = [
		'Q82955',   // politician
		'Q116',     // monarch
		'Q901',     // scientist
		'Q205375',  // inventor
		'Q483501',  // artist
		'Q36180',   // writer
		'Q2066131', // athlete
		'Q33999',   // actor
		'Q2526255', // film director
		'Q639669',  // musician
		'Q36834',   // composer
		'Q47064',   // military personnel
		'Q1397808', // resistance fighter
	];


	public function __construct(
		private readonly HttpClient $http,
	) {
	}


	public function find(string $nameItem, string $name, Country $country, array $excluded = []): ?Bearer
	{
		if (!preg_match('~^Q\d+$~', $nameItem) || !preg_match('~^[\p{L}\'’-]+$~u', $name) || preg_grep('~^Q\d+$~', $excluded, PREG_GREP_INVERT) !== []) {
			throw new \InvalidArgumentException("Invalid name item, name or exclusion: $nameItem, $name");
		}
		$language = $country->language();
		$values = fn(array $items): string => implode(' ', array_map(fn(string $q): string => "wd:$q", $items));
		$birthCountry = self::BirthCountries[$country->value];
		$excludedOccupations = $values(self::ExcludedOccupations);
		$notableFields = $values(self::NotableFields);
		$excludedPeople = $excluded === [] ? '' : 'FILTER(?person NOT IN (' . implode(', ', array_map(fn(string $q): string => "wd:$q", $excluded)) . '))';
		$prefix = str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . ' ';
		$query = <<<SPARQL
			SELECT ?person ?sitelinks ?article (GROUP_CONCAT(DISTINCT ?occupationLabel; separator=", ") AS ?occupations) WHERE {
			  {
			    SELECT ?person ?sitelinks ?article WHERE {
			      {
			        SELECT DISTINCT ?person ?sitelinks ?article WHERE {
			          ?person wdt:P735 wd:$nameItem ; wdt:P31 wd:Q5 ; wikibase:sitelinks ?sitelinks ; wdt:P19 ?birthplace .
			          ?birthplace wdt:P17 wd:$birthCountry .
			          ?article schema:about ?person ; schema:isPartOf <https://$language.wikipedia.org/> .
			          $excludedPeople
			        }
			        ORDER BY DESC(?sitelinks)
			        LIMIT 50
			      }
			      FILTER NOT EXISTS { ?person wdt:P106/wdt:P279* ?bad . VALUES ?bad { $excludedOccupations } }
			      BIND(EXISTS { ?person wdt:P106/wdt:P279* ?field . VALUES ?field { $notableFields } } AS ?notable)
			      FILTER(?notable || NOT EXISTS { ?person wdt:P1399 ?crime })
			      OPTIONAL { ?person rdfs:label ?label . FILTER(LANG(?label) = "$language") }
			      FILTER(COALESCE(STRSTARTS(STR(?label), "$prefix"), false))
			    }
			    ORDER BY DESC(?notable) DESC(?sitelinks)
			    LIMIT 1
			  }
			  OPTIONAL { ?person wdt:P106 ?occupation . ?occupation rdfs:label ?occupationLabel . FILTER(LANG(?occupationLabel) = "$language") }
			}
			GROUP BY ?person ?sitelinks ?article
			SPARQL;

		try {
			$response = $this->http->get(self::Endpoint . '?' . http_build_query(['query' => $query, 'format' => 'json']), ['Accept' => 'application/sparql-results+json']);
		} catch (HttpException $e) {
			throw new SourceException($e->getMessage(), 0, $e);
		}
		if ($response->status !== 200) {
			throw new SourceException("Wikidata Query Service: HTTP {$response->status} for $nameItem");
		}
		try {
			$data = Json::decode($response->body, forceArrays: true);
		} catch (JsonException $e) {
			throw new SourceException('Wikidata Query Service: invalid JSON', 0, $e);
		}

		$row = $data['results']['bindings'][0] ?? null;
		if (!is_array($row)) {
			return null;
		}
		$person = (string) ($row['person']['value'] ?? '');
		$article = rawurldecode(str_replace('_', ' ', (string) preg_replace('~^https://[a-z]+\.wikipedia\.org/wiki/~', '', (string) ($row['article']['value'] ?? ''))));
		if (!preg_match('~/(Q\d+)$~', $person, $m) || $article === '') {
			throw new SourceException("Wikidata Query Service: unexpected row for $nameItem");
		}
		$displayName = (string) preg_replace('~ \([^()]+\)$~u', '', $article); // „Jan Kubiš (voják)“ → „Jan Kubiš“
		// for the human review; six are enough to recognise the person
		$occupations = implode(', ', array_slice(array_filter(explode(', ', (string) ($row['occupations']['value'] ?? ''))), 0, 6));
		return new Bearer($m[1], $displayName, $article, (int) ($row['sitelinks']['value'] ?? 0), $occupations === '' ? null : $occupations);
	}
}
