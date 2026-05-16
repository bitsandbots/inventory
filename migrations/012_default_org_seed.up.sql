INSERT INTO `orgs` (`id`, `name`, `slug`, `created_at`)
VALUES (1, 'Default Organization', 'default', NOW());

INSERT INTO `org_members` (`org_id`, `user_id`, `role`)
SELECT 1,
       u.id,
       CASE u.user_level
           WHEN 1 THEN 'owner'
           WHEN 2 THEN 'admin'
           ELSE        'member'
       END
FROM `users` u
WHERE u.deleted_at IS NULL;
