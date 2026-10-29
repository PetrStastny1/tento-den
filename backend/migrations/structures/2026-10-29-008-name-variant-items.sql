-- Items a name item is said to be the same as (Wikidata P460) or the given name for the other gender
-- (P1560): one of the data rules for recognising a variant of another name of the same day.

ALTER TABLE name_infos ADD COLUMN related_items TEXT NULL AFTER wikidata_item;
