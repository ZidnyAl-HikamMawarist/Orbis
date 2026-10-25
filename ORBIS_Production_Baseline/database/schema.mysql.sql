-- ORBIS MySQL 8.x schema reference draft.
-- Laravel migrations are canonical. Review on the exact target MySQL version before release.
-- IDs use CHAR(26) ULIDs (uppercase Crockford base32) for consistent compact identifiers.
-- Store timestamps in UTC; render using companies.timezone.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  email_verified_at DATETIME(6) NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE companies (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
  status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_companies_slug (slug),
  UNIQUE KEY uq_companies_id_id (id, id),
  KEY ix_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE company_user (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  role ENUM('owner','company_admin','operations_manager','branch_manager','department_lead','staff','auditor') NOT NULL,
  status ENUM('invited','active','inactive') NOT NULL DEFAULT 'invited',
  invited_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  joined_at DATETIME(6) NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_company_user (company_id, user_id),
  UNIQUE KEY uq_membership_company_id (company_id, id),
  KEY ix_membership_user_status (user_id, status),
  CONSTRAINT fk_company_user_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_company_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_membership_inviter_same_company FOREIGN KEY (company_id, invited_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE company_invitations (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email VARCHAR(254) NOT NULL,
  role ENUM('owner','company_admin','operations_manager','branch_manager','department_lead','staff','auditor') NOT NULL DEFAULT 'staff',
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  invited_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  status ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
  expires_at DATETIME(6) NOT NULL,
  accepted_at DATETIME(6) NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_invitation_token_hash (token_hash),
  KEY ix_invitation_lookup (company_id, email, status),
  CONSTRAINT fk_invitation_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invitation_inviter_same_company FOREIGN KEY (company_id, invited_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE branches (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  address VARCHAR(500) NULL,
  timezone VARCHAR(64) NULL,
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_branch_company_id (company_id, id),
  UNIQUE KEY uq_branch_code (company_id, code),
  KEY ix_branch_status (company_id, status),
  CONSTRAINT fk_branch_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE departments (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_department_company_id (company_id, id),
  UNIQUE KEY uq_department_code (company_id, branch_id, code),
  KEY ix_department_branch_status (company_id, branch_id, status),
  CONSTRAINT fk_department_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_department_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE company_invitations (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email VARCHAR(254) NOT NULL,
  role ENUM('owner','company_admin','operations_manager','branch_manager','department_lead','staff','auditor') NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  invited_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  accepted_at DATETIME(6) NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_invitation_token (token_hash),
  UNIQUE KEY uq_company_email_pending (company_id, email, accepted_at),
  KEY ix_invitation_expiry (company_id, expires_at),
  CONSTRAINT fk_invitation_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invitation_inviter_same_company FOREIGN KEY (company_id, invited_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_invitation_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_invitation_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE projects (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  lead_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('planned','active','on_hold','completed','cancelled','archived') NOT NULL DEFAULT 'planned',
  progress_mode ENUM('manual','task_derived') NOT NULL DEFAULT 'task_derived',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  start_date DATE NULL,
  due_date DATE NULL,
  completed_at DATETIME(6) NULL,
  created_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  archived_at DATETIME(6) NULL,
  UNIQUE KEY uq_project_company_id (company_id, id),
  KEY ix_project_scope_status (company_id, branch_id, department_id, status),
  KEY ix_project_due (company_id, due_date, status),
  CONSTRAINT chk_project_progress CHECK (progress_percent BETWEEN 0 AND 100),
  CONSTRAINT chk_project_dates CHECK (due_date IS NULL OR start_date IS NULL OR due_date >= start_date),
  CONSTRAINT fk_project_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_project_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_project_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_project_lead_same_company FOREIGN KEY (company_id, lead_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_project_creator_same_company FOREIGN KEY (company_id, created_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tasks (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  project_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  assignee_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  status ENUM('backlog','todo','in_progress','blocked','done','cancelled') NOT NULL DEFAULT 'todo',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  due_at DATETIME(6) NULL,
  completed_at DATETIME(6) NULL,
  created_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  archived_at DATETIME(6) NULL,
  UNIQUE KEY uq_task_company_id (company_id, id),
  KEY ix_task_dashboard (company_id, status, due_at),
  KEY ix_task_assignee (company_id, assignee_membership_id, status, due_at),
  KEY ix_task_scope (company_id, branch_id, department_id, status),
  KEY ix_task_project (company_id, project_id, status),
  CONSTRAINT fk_task_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_task_project_same_company FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_task_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_task_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_task_assignee_same_company FOREIGN KEY (company_id, assignee_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_task_creator_same_company FOREIGN KEY (company_id, created_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE task_status_history (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  task_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  from_status ENUM('backlog','todo','in_progress','blocked','done','cancelled') NULL,
  to_status ENUM('backlog','todo','in_progress','blocked','done','cancelled') NOT NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME(6) NOT NULL,
  UNIQUE KEY uq_task_history_company_id (company_id, id),
  KEY ix_task_history_timeline (company_id, task_id, created_at),
  CONSTRAINT fk_history_task_same_company FOREIGN KEY (company_id, task_id) REFERENCES tasks(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_history_actor_same_company FOREIGN KEY (company_id, actor_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE task_comments (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  task_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  author_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_comment_company_id (company_id, id),
  KEY ix_comment_task_time (company_id, task_id, created_at),
  CONSTRAINT chk_comment_body CHECK (CHAR_LENGTH(body) BETWEEN 1 AND 10000),
  CONSTRAINT fk_comment_task_same_company FOREIGN KEY (company_id, task_id) REFERENCES tasks(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_comment_author_same_company FOREIGN KEY (company_id, author_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE alerts (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  project_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  task_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  source VARCHAR(80) NOT NULL,
  dedupe_key VARCHAR(190) NULL,
  title VARCHAR(200) NOT NULL,
  message VARCHAR(1000) NULL,
  acknowledged_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  acknowledged_at DATETIME(6) NULL,
  resolved_by_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  resolved_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_alert_company_id (company_id, id),
  UNIQUE KEY uq_alert_dedupe (company_id, source, dedupe_key),
  KEY ix_alert_inbox (company_id, status, severity, created_at),
  KEY ix_alert_branch (company_id, branch_id, status, created_at),
  CONSTRAINT fk_alert_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_project_same_company FOREIGN KEY (company_id, project_id) REFERENCES projects(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_task_same_company FOREIGN KEY (company_id, task_id) REFERENCES tasks(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_ack_by_same_company FOREIGN KEY (company_id, acknowledged_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_alert_resolve_by_same_company FOREIGN KEY (company_id, resolved_by_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE kpi_definitions (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  label VARCHAR(160) NOT NULL,
  description TEXT NULL,
  unit VARCHAR(40) NOT NULL DEFAULT 'count',
  aggregation ENUM('realtime','hourly','daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  definition_version INT UNSIGNED NOT NULL DEFAULT 1,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_kpi_definition_version (company_id, `key`, definition_version),
  UNIQUE KEY uq_kpi_definition_company_id (company_id, id),
  CONSTRAINT fk_kpi_definition_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE kpi_snapshots (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  kpi_definition_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  window_start DATETIME(6) NOT NULL,
  window_end DATETIME(6) NOT NULL,
  value_decimal DECIMAL(20,6) NULL,
  value_json JSON NULL,
  freshness ENUM('fresh','stale','unavailable') NOT NULL DEFAULT 'fresh',
  computed_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NULL,
  UNIQUE KEY uq_kpi_snapshot_company_id (company_id, id),
  UNIQUE KEY uq_kpi_snapshot_window (company_id, kpi_definition_id, branch_id, department_id, window_start, window_end),
  KEY ix_kpi_snapshot_lookup (company_id, kpi_definition_id, window_end),
  CONSTRAINT chk_kpi_window CHECK (window_end > window_start),
  CONSTRAINT fk_kpi_snapshot_definition_same_company FOREIGN KEY (company_id, kpi_definition_id) REFERENCES kpi_definitions(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_kpi_snapshot_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_kpi_snapshot_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE world_configs (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  branch_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  department_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  entity_type ENUM('company','branch','department') NOT NULL,
  -- For company entity use company_id as entity identity; for branch/department use corresponding FK.
  position_x DECIMAL(10,3) NOT NULL DEFAULT 0,
  position_y DECIMAL(10,3) NOT NULL DEFAULT 0,
  position_z DECIMAL(10,3) NOT NULL DEFAULT 0,
  rotation_y DECIMAL(10,3) NOT NULL DEFAULT 0,
  scale DECIMAL(8,3) NOT NULL DEFAULT 1,
  layout_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NULL,
  updated_at DATETIME(6) NULL,
  UNIQUE KEY uq_world_config_company_id (company_id, id),
  UNIQUE KEY uq_world_config_branch (company_id, branch_id),
  UNIQUE KEY uq_world_config_department (company_id, department_id),
  CONSTRAINT chk_world_scale CHECK (scale > 0),
  CONSTRAINT fk_world_config_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_world_config_branch_same_company FOREIGN KEY (company_id, branch_id) REFERENCES branches(company_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_world_config_department_same_company FOREIGN KEY (company_id, department_id) REFERENCES departments(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE audit_events (
  id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  company_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_membership_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  action VARCHAR(100) NOT NULL,
  resource_type VARCHAR(100) NOT NULL,
  resource_id CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NULL,
  request_id VARCHAR(100) NULL,
  metadata JSON NULL,
  created_at DATETIME(6) NOT NULL,
  KEY ix_audit_company_time (company_id, created_at),
  KEY ix_audit_resource (company_id, resource_type, resource_id, created_at),
  CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_actor_same_company FOREIGN KEY (company_id, actor_membership_id) REFERENCES company_user(company_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Review notes & Reconciled Decision Baseline (OD-001..OD-013):
-- 1) IDs: All primary and foreign keys standardized on CHAR(26) ULIDs (OD-002) matching Laravel 13 HasUlids.
-- 2) Invitations: Added `company_invitations` table with SHA-256 token hashing and 72-hour expiry (OD-006).
-- 3) Departments: In Laravel migrations, implement generated virtual column scope_key = CONCAT(company_id, ':', COALESCE(branch_id, '__GLOBAL__'), ':', code) with unique index to eliminate MySQL multiple-NULL leakage for company-wide departments.
-- 4) Projects: progress_mode is frozen to 'task_derived' exclusively for MVP; manual mode is disabled (OD-003).
-- 5) KPIs: Limited to 5 operational metrics (OD-004); mock financial/POS data from prototype is excluded from MVP schema.
-- 6) Audit Events: Protected via least-privilege DB grants (SELECT, INSERT only) and 12-month retention policy (OD-008).

