ALTER TABLE `categories`
    DROP FOREIGN KEY `fk_categories_org`,
    DROP KEY `idx_categories_org`,
    DROP INDEX `uq_categories_org_name`,
    ADD UNIQUE KEY `name` (`name`),
    DROP COLUMN `org_id`;
