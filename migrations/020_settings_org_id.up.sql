ALTER TABLE `settings`
    ADD COLUMN `org_id` INT(11) UNSIGNED NOT NULL DEFAULT 1 FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`org_id`, `setting_key`),
    ADD CONSTRAINT `fk_settings_org`
        FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`) ON DELETE CASCADE;
