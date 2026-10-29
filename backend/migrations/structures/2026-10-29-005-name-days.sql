-- Name days: Czech ones from the "Svátky" section of the cs.wikipedia day page (the same revision as
-- the events), Slovak ones from the sk.wikipedia day page (its own revision). The Slovak line of the
-- cs page is stored too (source_wiki = cs, country = sk), only to compare the two calendars.
-- name_infos: what is shown about a name in a mode – the introduction of its article and a known
-- bearer, both from the Wikipedia of that mode (cs for CZ, sk for SK).

CREATE TABLE name_days (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	month TINYINT UNSIGNED NOT NULL,
	day TINYINT UNSIGNED NOT NULL,
	source_wiki ENUM('cs', 'sk') NOT NULL,
	country ENUM('cz', 'sk') NOT NULL,
	name VARCHAR(64) NOT NULL,
	line TINYINT UNSIGNED NOT NULL,
	position TINYINT UNSIGNED NOT NULL,
	note VARCHAR(500) NULL,
	source_title VARCHAR(64) NOT NULL,
	source_revision_id INT UNSIGNED NOT NULL,
	fetched_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_name_days (month, day, source_wiki, country, name),
	KEY idx_name_days_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE name_infos (
	language ENUM('cs', 'sk') NOT NULL,
	name VARCHAR(64) NOT NULL,
	wikidata_item VARCHAR(16) NULL,
	article_title VARCHAR(255) NULL,
	extract TEXT NULL,
	article_revision_id INT UNSIGNED NULL,
	bearer_item VARCHAR(16) NULL,
	bearer_name VARCHAR(255) NULL,
	bearer_article VARCHAR(255) NULL,
	bearer_sitelinks SMALLINT UNSIGNED NULL,
	checked_at DATETIME NOT NULL,
	PRIMARY KEY (language, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
