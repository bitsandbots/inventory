ALTER TABLE `customers`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_customers_org` (`org_id`),
    ADD CONSTRAINT `fk_customers_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);

ALTER TABLE `customers`
    DROP INDEX `name`,
    ADD UNIQUE KEY `uq_customers_org_name` (`org_id`, `name`);
