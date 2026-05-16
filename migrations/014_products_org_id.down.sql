ALTER TABLE `products`
    DROP FOREIGN KEY `fk_products_org`,
    DROP KEY `idx_products_org`,
    DROP INDEX `uq_products_org_name`,
    ADD UNIQUE KEY `name` (`name`),
    DROP COLUMN `org_id`;
