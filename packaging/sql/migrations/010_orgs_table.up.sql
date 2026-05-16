CREATE TABLE `orgs` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)     NOT NULL,
    `slug`       VARCHAR(60)      NOT NULL,
    `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP        NULL DEFAULT NULL,
    `deleted_by` INT(11) UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orgs_slug` (`slug`),
    KEY `idx_orgs_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_orgs_deleted_by`
        FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
