ALTER TABLE `settings`
    DROP FOREIGN KEY `fk_settings_org`,
    DROP PRIMARY KEY,
    DROP COLUMN `org_id`,
    ADD PRIMARY KEY (`setting_key`);
