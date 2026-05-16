CREATE TABLE `org_members` (
    `org_id`    INT(11) UNSIGNED NOT NULL,
    `user_id`   INT(11) UNSIGNED NOT NULL,
    `role`      ENUM('owner','admin','member') NOT NULL,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`org_id`, `user_id`),
    KEY `idx_org_members_user` (`user_id`),
    CONSTRAINT `fk_org_members_org`
        FOREIGN KEY (`org_id`)  REFERENCES `orgs`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_org_members_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
