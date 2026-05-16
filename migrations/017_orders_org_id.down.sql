ALTER TABLE `orders`
    DROP FOREIGN KEY `fk_orders_org`,
    DROP KEY `idx_orders_org`,
    DROP COLUMN `org_id`;
