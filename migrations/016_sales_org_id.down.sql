ALTER TABLE `sales`
    DROP FOREIGN KEY `fk_sales_org`,
    DROP KEY `idx_sales_org`,
    DROP COLUMN `org_id`;
