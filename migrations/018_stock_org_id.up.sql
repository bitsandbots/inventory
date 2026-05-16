ALTER TABLE `stock`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_stock_org` (`org_id`),
    ADD CONSTRAINT `fk_stock_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);
