CREATE TABLE IF NOT EXISTS rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_rate_expiry (expires_at)
) ENGINE=InnoDB;
