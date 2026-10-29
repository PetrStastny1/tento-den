-- Occupations of the chosen bearer (Wikidata P106, labels in the language of the mode) for the human review in docs/nositele.md.

ALTER TABLE name_infos ADD COLUMN bearer_occupations VARCHAR(500) NULL AFTER bearer_sitelinks;
