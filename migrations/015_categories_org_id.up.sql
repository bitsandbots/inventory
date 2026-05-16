ALTER TABLE `categories`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_categories_org` (`org_id`),
    ADD CONSTRAINT `fk_categories_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);

ALTER TABLE `categories`
    DROP INDEX `name`,
    ADD UNIQUE KEY `uq_categories_org_name` (`org_id`, `name`);
