ALTER TABLE `sales`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_sales_org` (`org_id`),
    ADD CONSTRAINT `fk_sales_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);
