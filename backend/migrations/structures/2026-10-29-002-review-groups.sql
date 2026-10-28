-- Events marked for review form a group: the stored originals and the imported replacements.
-- When exactly the original text returns and no replacement is on the page any more, the group
-- is resolved automatically (originals active again, replacements missing).

ALTER TABLE events
	ADD COLUMN review_group INT UNSIGNED NULL AFTER review_note,
	ADD COLUMN review_role ENUM('original', 'replacement') NULL AFTER review_group,
	ADD KEY idx_events_review_group (review_group);
