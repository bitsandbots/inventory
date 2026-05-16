ALTER TABLE `stock`
    DROP FOREIGN KEY `fk_stock_org`,
    DROP KEY `idx_stock_org`,
    DROP COLUMN `org_id`;
