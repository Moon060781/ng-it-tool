-- Run this file in phpMyAdmin after selecting database noorgeec_it.
-- Stores approved correction pairs only; no API keys, audio, or full scripts.
CREATE TABLE IF NOT EXISTS `vo_pronunciation_corrections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_username` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `pair_hash` BINARY(32) NOT NULL,
    `original_text` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `corrected_text` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `context_text` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
    `source` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual_review',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `use_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_owner_pair_hash` (`owner_username`, `pair_hash`),
    KEY `idx_owner_updated` (`owner_username`, `updated_at`),
    KEY `idx_owner_original` (`owner_username`, `original_text`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
