ALTER TABLE `customers`
    DROP FOREIGN KEY `fk_customers_org`,
    DROP KEY `idx_customers_org`,
    DROP INDEX `uq_customers_org_name`,
    ADD UNIQUE KEY `name` (`name`),
    DROP COLUMN `org_id`;
