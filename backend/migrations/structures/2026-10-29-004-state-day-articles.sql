-- Introductions of the Wikipedia articles shown on the cards of state days without an event
-- (Act No. 245/2000 Coll., config/state-days.neon), with the revision for attribution.

CREATE TABLE state_day_articles (
	requested_title VARCHAR(255) NOT NULL,
	title VARCHAR(255) NOT NULL,
	extract TEXT NOT NULL,
	revision_id INT UNSIGNED NOT NULL,
	fetched_at DATETIME NOT NULL,
	PRIMARY KEY (requested_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
