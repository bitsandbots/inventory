-- Migration 002 — Add failed_logins table for login rate limiting.
--
-- Stores one row per failed login attempt, indexed by IP. The application
-- checks recent attempts before authenticating and clears the row set on
-- successful login.
--
-- Reverse: see 002_failed_logins.down.sql

CREATE TABLE IF NOT EXISTS `failed_logins` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip` varchar(45) NOT NULL,
    `username_attempted` varchar(100) DEFAULT NULL,
    `attempted_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_failed_logins_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'failed_logins table created' AS step;
SHOW CREATE TABLE failed_logins;
