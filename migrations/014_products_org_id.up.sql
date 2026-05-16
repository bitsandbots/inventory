ALTER TABLE `products`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,
    ADD KEY `idx_products_org` (`org_id`),
    ADD CONSTRAINT `fk_products_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`);

ALTER TABLE `products`
    DROP INDEX `name`,
    ADD UNIQUE KEY `uq_products_org_name` (`org_id`, `name`);
