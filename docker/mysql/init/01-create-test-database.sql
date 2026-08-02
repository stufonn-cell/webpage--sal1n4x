CREATE DATABASE IF NOT EXISTS psiclinic_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON psiclinic_test.* TO 'psiclinic'@'%';
FLUSH PRIVILEGES;
