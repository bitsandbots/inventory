ALTER TABLE `users`
    ADD COLUMN `last_active_org_id` INT(11) UNSIGNED NULL DEFAULT NULL,
    ADD CONSTRAINT `fk_users_last_active_org`
        FOREIGN KEY (`last_active_org_id`) REFERENCES `orgs` (`id`) ON DELETE SET NULL;
