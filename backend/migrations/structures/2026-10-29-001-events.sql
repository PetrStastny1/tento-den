-- Events from cs.wikipedia day pages ("Události" → Česko / Svět), their articles and import state.
-- Years: -1 = 1 př. n. l. (BC), there is no year 0. All DATETIME values are UTC.

CREATE TABLE import_runs (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	started_at DATETIME NOT NULL,
	finished_at DATETIME NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE articles (
	page_id INT UNSIGNED NOT NULL,
	title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
	revision_id INT UNSIGNED NOT NULL,
	wikibase_item VARCHAR(16) CHARACTER SET ascii NULL,
	fetched_at DATETIME NOT NULL,
	PRIMARY KEY (page_id),
	KEY idx_articles_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE events (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	month TINYINT UNSIGNED NOT NULL,
	day TINYINT UNSIGNED NOT NULL,
	year SMALLINT NOT NULL,
	section ENUM('cz', 'world') NOT NULL,
	position SMALLINT UNSIGNED NOT NULL,
	text TEXT NOT NULL,
	text_hash CHAR(64) CHARACTER SET ascii NOT NULL,
	source_title VARCHAR(64) NOT NULL,
	source_revision_id INT UNSIGNED NOT NULL,
	has_primary_article TINYINT(1) NOT NULL,
	status ENUM('active', 'missing', 'needs_review') NOT NULL DEFAULT 'active',
	review_note VARCHAR(500) NULL,
	missing_since DATETIME NULL,
	fetched_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY idx_events_day (month, day, section, status),
	CONSTRAINT chk_events_year CHECK (year <> 0),
	CONSTRAINT chk_events_month CHECK (month BETWEEN 1 AND 12),
	CONSTRAINT chk_events_day CHECK (day BETWEEN 1 AND 31)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE event_articles (
	event_id INT UNSIGNED NOT NULL,
	page_id INT UNSIGNED NOT NULL,
	position TINYINT UNSIGNED NOT NULL,
	is_primary TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (event_id, page_id),
	KEY idx_event_articles_page (page_id),
	CONSTRAINT fk_event_articles_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
	CONSTRAINT fk_event_articles_article FOREIGN KEY (page_id) REFERENCES articles (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE import_days (
	month TINYINT UNSIGNED NOT NULL,
	day TINYINT UNSIGNED NOT NULL,
	status ENUM('ok', 'error') NOT NULL,
	last_attempt_at DATETIME NOT NULL,
	last_success_at DATETIME NULL,
	source_revision_id INT UNSIGNED NULL,
	events_count SMALLINT UNSIGNED NULL,
	error_message TEXT NULL,
	last_run_id INT UNSIGNED NULL,
	PRIMARY KEY (month, day),
	CONSTRAINT fk_import_days_run FOREIGN KEY (last_run_id) REFERENCES import_runs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
