-- ============================================================
-- pixel-life MySQL 初始化脚本
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pixellife`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'pixellife'@'localhost' IDENTIFIED BY '你的密码';
GRANT ALL PRIVILEGES ON `pixellife`.* TO 'pixellife'@'localhost';
FLUSH PRIVILEGES;

USE `pixellife`;

CREATE TABLE IF NOT EXISTS `family` (
    `id`          INT PRIMARY KEY AUTO_INCREMENT,
    `name`        VARCHAR(50)  NOT NULL DEFAULT '我的目标',
    `level`       INT          NOT NULL DEFAULT 1,
    `exp`         INT          NOT NULL DEFAULT 0,
    `avatar`      VARCHAR(10)  NOT NULL DEFAULT '🧍',
    `job`         VARCHAR(100),
    `age`         INT,
    `target_income` DOUBLE     DEFAULT 0,
    `api_url`     VARCHAR(255) DEFAULT ''  COMMENT 'AI 接口地址',
    `api_key`     VARCHAR(255) DEFAULT ''  COMMENT 'AI Key',
    `model_name`  VARCHAR(100) DEFAULT 'gpt-3.5-turbo' COMMENT '模型名称',
    `year_target` DOUBLE      DEFAULT 1000000 COMMENT '年度目标金额',
    `fixed_income` TEXT       COMMENT '固定收入 JSON',
    `city`          VARCHAR(50) DEFAULT '北京' COMMENT '城市',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `family_members` (
    `id`              INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`       INT          NOT NULL DEFAULT 1,
    `name`            VARCHAR(50)  NOT NULL DEFAULT '我',
    `role`            VARCHAR(20)  NOT NULL DEFAULT 'self',
    `avatar`          VARCHAR(10)  NOT NULL DEFAULT '🧍',
    `age`             INT,
    `job`             VARCHAR(100),
    `monthly_income`  DOUBLE      DEFAULT 0,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `goals` (
    `id`              INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`       INT          NOT NULL DEFAULT 1,
    `title`           VARCHAR(200) NOT NULL,
    `type`            VARCHAR(20)  NOT NULL DEFAULT 'annual',
    `target_amount`   DOUBLE       NOT NULL DEFAULT 0,
    `current_amount`  DOUBLE       NOT NULL DEFAULT 0,
    `start_date`      DATE,
    `end_date`        DATE,
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'active',
    `parent_id`       INT,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`),
    FOREIGN KEY (`parent_id`) REFERENCES `goals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `income_records` (
    `id`          INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`   INT          NOT NULL DEFAULT 1,
    `amount`      DOUBLE       NOT NULL,
    `type`        VARCHAR(30)  NOT NULL DEFAULT '固定收入',
    `date`        DATE         NOT NULL,
    `remark`      VARCHAR(200),
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `expense_records` (
    `id`          INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`   INT          NOT NULL DEFAULT 1,
    `amount`      DOUBLE       NOT NULL,
    `type`        VARCHAR(30)  NOT NULL DEFAULT '其他',
    `date`        DATE         NOT NULL,
    `remark`      VARCHAR(200),
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_tasks` (
    `id`              INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`       INT          NOT NULL DEFAULT 1,
    `goal_id`         INT,
    `title`           VARCHAR(200) NOT NULL,
    `description`     TEXT,
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `task_date`       DATE         NOT NULL,
    `priority`        INT          NOT NULL DEFAULT 2,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`    DATETIME,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`),
    FOREIGN KEY (`goal_id`) REFERENCES `goals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory` (
    `id`          INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`   INT          NOT NULL DEFAULT 1,
    `mkey`        VARCHAR(100) NOT NULL,
    `value`       TEXT,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`),
    UNIQUE KEY `uk_family_key` (`family_id`, `mkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dialogue_records` (
    `id`          INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`   INT          NOT NULL DEFAULT 1,
    `role`        VARCHAR(20)  NOT NULL DEFAULT 'ai',
    `content`     TEXT,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_activities` (
    `id`              INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`       INT          NOT NULL DEFAULT 1,
    `activity_type`   VARCHAR(30)  NOT NULL,
    `description`     VARCHAR(200),
    `started_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at`        DATETIME,
    `duration_minutes` INT,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_reminders` (
    `id`              INT PRIMARY KEY AUTO_INCREMENT,
    `family_id`       INT          NOT NULL DEFAULT 1,
    `title`           VARCHAR(200) NOT NULL,
    `message`         TEXT         NOT NULL,
    `type`            VARCHAR(20)  NOT NULL DEFAULT 'goal',
    `related_goal_id` INT,
    `remind_at`       DATETIME     NOT NULL,
    `is_read`         TINYINT(1)   NOT NULL DEFAULT 0,
    `is_done`         TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `family`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`related_goal_id`) REFERENCES `goals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `family` (id, name, avatar, job) VALUES (1, '我的目标', '🧍', '职场人');
INSERT IGNORE INTO `family_members` (family_id, name, role, avatar) VALUES (1, '我', 'self', '🧍');

SELECT '建表完成！' AS msg;
SHOW TABLES;
