ALTER TABLE `users`
    DROP FOREIGN KEY `fk_users_last_active_org`,
    DROP COLUMN `last_active_org_id`;
