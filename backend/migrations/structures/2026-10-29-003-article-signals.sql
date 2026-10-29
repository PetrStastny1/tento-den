-- Selection signal: number of Wikipedia language editions (Wikidata sitelinks) of an article.

ALTER TABLE articles
	ADD COLUMN sitelinks SMALLINT UNSIGNED NULL AFTER wikibase_item,
	ADD COLUMN signals_fetched_at DATETIME NULL AFTER sitelinks;
