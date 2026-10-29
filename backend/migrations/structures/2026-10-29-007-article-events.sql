-- Whether an article is itself an event of a big type (Wikidata P31/P279* to a configured class,
-- config/anniversaries.neon) and the years of its date (P585 point in time, P580 start, P571 inception),
-- for round anniversaries.

ALTER TABLE articles
	ADD COLUMN event_class VARCHAR(16) NULL AFTER sitelinks,
	ADD COLUMN event_years VARCHAR(255) NULL AFTER event_class,
	ADD COLUMN event_checked_at DATETIME NULL AFTER event_years;
