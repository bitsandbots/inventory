-- Migration 002 reverse — drop failed_logins table.

DROP TABLE IF EXISTS `failed_logins`;
SELECT 'failed_logins table dropped' AS step;
