CREATE DATABASE IF NOT EXISTS quizhell CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quizhell;

CREATE TABLE rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_rate_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE quizzes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    public_id CHAR(32) NOT NULL UNIQUE,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    rage_level ENUM('mild', 'annoying', 'hell') NOT NULL DEFAULT 'mild',
    timer_minutes TINYINT UNSIGNED NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_timer CHECK (timer_minutes IS NULL OR timer_minutes BETWEEN 1 AND 30)
) ENGINE=InnoDB;

CREATE TABLE questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id BIGINT UNSIGNED NOT NULL,
    prompt TEXT NOT NULL,
    type ENUM('multiple_choice', 'true_false') NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_question_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_question_order (quiz_id, position)
) ENGINE=InnoDB;

CREATE TABLE answer_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(500) NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY idx_option_position (question_id, position)
) ENGINE=InnoDB;

CREATE TABLE quiz_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id BIGINT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    nickname VARCHAR(60) NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    correct_count TINYINT UNSIGNED NULL,
    question_count TINYINT UNSIGNED NULL,
    score TINYINT UNSIGNED NULL,
    category ENUM('Low', 'Mid', 'Good') NULL,
    CONSTRAINT fk_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_attempt_quiz (quiz_id, finished_at),
    CONSTRAINT chk_score CHECK (score IS NULL OR score BETWEEN 1 AND 10)
) ENGINE=InnoDB;

CREATE TABLE player_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    question_revision INT UNSIGNED NOT NULL,
    selected_position TINYINT UNSIGNED NULL,
    answered_at DATETIME NULL,
    question_prompt TEXT NULL,
    is_correct TINYINT(1) NULL,
    counted TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    UNIQUE KEY idx_answer_question (attempt_id, question_id)
) ENGINE=InnoDB;
