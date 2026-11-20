CREATE DATABASE IF NOT EXISTS personal_os
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE personal_os;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS focus_sessions;
DROP TABLE IF EXISTS backup_schedules;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS goals;
DROP TABLE IF EXISTS workspace_events;
DROP TABLE IF EXISTS workspace_members;
DROP TABLE IF EXISTS workspaces;
DROP TABLE IF EXISTS habit_logs;
DROP TABLE IF EXISTS habits;
DROP TABLE IF EXISTS invitations;
DROP TABLE IF EXISTS reminders;
DROP TABLE IF EXISTS team_memberships;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  avatar_url VARCHAR(255) DEFAULT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Istanbul',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status ENUM('todo', 'in_progress', 'completed', 'archived') NOT NULL DEFAULT 'todo',
  priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  category VARCHAR(60) NOT NULL DEFAULT 'Personal',
  due_at DATETIME NULL,
  recurrence ENUM('none', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'none',
  next_recurrence_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_user_status (user_id, status),
  KEY idx_tasks_due_at (due_at),
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  content TEXT NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT 'violet',
  is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_user_pinned (user_id, is_pinned),
  CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE system_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
  message VARCHAR(255) NOT NULL,
  context_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_user_created (user_id, created_at),
  KEY idx_logs_level_created (level, created_at),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team_memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  team_name VARCHAR(100) NOT NULL DEFAULT 'Personal',
  role ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_membership (user_id, team_name),
  CONSTRAINT fk_team_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reminders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  remind_at DATETIME NOT NULL,
  is_sent BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reminders_user_due (user_id, remind_at),
  CONSTRAINT fk_reminders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reminders_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE habits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  recurrence ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
  target_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_habits_user_active (user_id, is_active),
  CONSTRAINT fk_habits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE habit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  habit_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_habit_log_day (habit_id, log_date),
  KEY idx_habit_logs_user_date (user_id, log_date),
  CONSTRAINT fk_habit_logs_habit FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_habit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inviter_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(191) NOT NULL,
  role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitation_token (token_hash),
  KEY idx_invitations_email (email),
  CONSTRAINT fk_invitations_inviter FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE workspaces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_workspaces_owner (owner_id),
  CONSTRAINT fk_workspaces_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE workspace_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  workspace_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_workspace_member (workspace_id, user_id),
  CONSTRAINT fk_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_workspace_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE workspace_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  workspace_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  message VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_workspace_events (workspace_id, created_at),
  CONSTRAINT fk_workspace_events_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_workspace_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE task_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  category VARCHAR(60) NOT NULL DEFAULT 'Personal',
  recurrence ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_templates_user (user_id),
  CONSTRAINT fk_task_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE automation_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  trigger_type VARCHAR(40) NOT NULL,
  trigger_value VARCHAR(100) NOT NULL,
  action_type VARCHAR(40) NOT NULL,
  action_value VARCHAR(100) NOT NULL,
  is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_automation_user_enabled (user_id,is_enabled),
  CONSTRAINT fk_automation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vault_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  encrypted_value VARBINARY(4096) NOT NULL,
  iv VARBINARY(16) NOT NULL,
  tag VARBINARY(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vault_user (user_id),
  CONSTRAINT fk_vault_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user_created (user_id,created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  target_value DECIMAL(12,2) NOT NULL DEFAULT 100,
  current_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL DEFAULT 'percent',
  target_date DATE NULL,
  status ENUM('active','completed','paused','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_goals_user_status (user_id,status),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  entry_date DATE NOT NULL,
  mood ENUM('great','good','okay','low','rough') NOT NULL DEFAULT 'okay',
  energy TINYINT UNSIGNED NOT NULL DEFAULT 3,
  gratitude TEXT NULL,
  reflection TEXT NULL,
  wins TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_journal_user_date (user_id,entry_date),
  CONSTRAINT fk_journal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE focus_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  status ENUM('running','completed','cancelled') NOT NULL DEFAULT 'running',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_focus_user_status (user_id,status),
  CONSTRAINT fk_focus_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_focus_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE backup_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  last_run_at DATETIME NULL,
  next_run_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_backup_schedule_user (user_id),
  CONSTRAINT fk_backup_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  email VARCHAR(191) NOT NULL,
  succeeded BOOLEAN NOT NULL DEFAULT FALSE,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_ip_time (ip_address,attempted_at),
  KEY idx_login_attempt_email_time (email,attempted_at)
) ENGINE=InnoDB;

INSERT INTO users (name, email, password_hash, role, avatar_url, timezone) VALUES
  ('Alex Morgan', 'alex@personal-os.local', '*UNPROVISIONED*', 'admin', 'https://i.pravatar.cc/160?img=12', 'Europe/Istanbul'),
  ('Jamie Chen', 'jamie@personal-os.local', '*UNPROVISIONED*', 'member', 'https://i.pravatar.cc/160?img=47', 'Europe/Istanbul');

INSERT INTO goals (user_id,title,description,target_value,current_value,unit,target_date,status) VALUES
  (1,'Build a consistent planning routine','Complete a short daily review and prepare the next focus block.',30,18,'days','2026-12-31','active'),
  (1,'Finish Personal OS release','Complete the production hardening and documentation.',100,92,'percent','2026-12-15','active'),
  (1,'Read every week','Maintain a steady reading rhythm.',12,5,'books','2027-01-31','active');

INSERT INTO tasks (user_id, title, description, status, priority, category, due_at, completed_at) VALUES
  (1, 'Review quarterly goals', 'Audit priorities and update the Q4 focus areas.', 'in_progress', 'high', 'Strategy', DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL),
  (1, 'Finish dashboard wireframe', 'Complete responsive states and handoff notes.', 'in_progress', 'urgent', 'Design', DATE_ADD(NOW(), INTERVAL 5 HOUR), NULL),
  (1, 'Morning deep work', 'Two-hour focus block for the launch brief.', 'completed', 'high', 'Work', NOW(), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
  (1, 'Book dentist appointment', 'Call the clinic and confirm the next available slot.', 'todo', 'medium', 'Personal', DATE_ADD(NOW(), INTERVAL 1 DAY), NULL),
  (1, 'Plan weekly meals', 'Create grocery list for the week.', 'todo', 'low', 'Health', DATE_ADD(NOW(), INTERVAL 2 DAY), NULL),
  (1, 'Sync with product team', 'Share progress and remove blockers.', 'completed', 'medium', 'Work', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO notes (user_id, title, content, color, is_pinned) VALUES
  (1, 'Launch ideas', 'Build a calm daily planning ritual with clear outcomes, low-friction capture, and a weekly review.', 'violet', TRUE),
  (1, 'Bookshelf', 'Read: The Creative Act, Four Thousand Weeks, and Designing Your Life. Add one useful idea per chapter.', 'amber', FALSE),
  (1, 'Weekly reflection', 'What gave me energy? What created unnecessary friction? Choose three changes for next week.', 'cyan', TRUE),
  (1, 'Project links', 'Dashboard prototype, research board, and final implementation checklist.', 'rose', FALSE);

INSERT INTO system_logs (user_id, event_type, level, message, context_json, ip_address) VALUES
  (1, 'dashboard.view', 'info', 'Dashboard loaded successfully', JSON_OBJECT('module', 'dashboard', 'load_ms', 42), '127.0.0.1'),
  (1, 'task.completed', 'info', 'Morning deep work completed', JSON_OBJECT('task_id', 3), '127.0.0.1'),
  (1, 'database.backup', 'info', 'Local database backup completed', JSON_OBJECT('size_mb', 18.4), '127.0.0.1'),
  (1, 'auth.login', 'info', 'Administrator signed in', JSON_OBJECT('method', 'local'), '127.0.0.1'),
  (NULL, 'system.health', 'warning', 'No external service calls detected', JSON_OBJECT('status', 'offline_capable'), '127.0.0.1');

INSERT INTO habits (user_id, name, recurrence, target_count) VALUES
  (1, 'Morning planning', 'daily', 1),
  (1, 'Read for 20 minutes', 'daily', 1),
  (1, 'Weekly reflection', 'weekly', 1);

INSERT INTO habit_logs (habit_id, user_id, log_date, count) VALUES
  (1, 1, CURDATE(), 1),
  (2, 1, CURDATE(), 1);
