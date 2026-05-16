ALTER TABLE `media`
    DROP FOREIGN KEY `fk_media_org`,
    DROP KEY `idx_media_org`,
    DROP COLUMN `org_id`;
