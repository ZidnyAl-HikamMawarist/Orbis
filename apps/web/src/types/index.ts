export interface User {
  id: string
  name: string
  email: string
  created_at?: string
}

export interface Company {
  id: string
  name: string
  slug: string
  status: 'active' | 'suspended' | 'archived'
}

export interface Membership {
  id: string
  company_id: string
  user_id: string
  role: 'owner' | 'company_admin' | 'operations_manager' | 'branch_manager' | 'department_lead' | 'staff' | 'auditor'
  status: 'invited' | 'active' | 'inactive'
  company?: Company
  user?: User
}

export interface Branch {
  id: string
  company_id: string
  code: string
  name: string
  address?: string | null
  timezone?: string | null
  status: 'active' | 'inactive' | 'archived'
  departments_count?: number
  active_tasks_count?: number
  departments?: Department[]
  operational_status?: 'operational' | 'warning' | 'critical'
}

export interface Department {
  id: string
  company_id: string
  branch_id?: string | null
  code: string
  name: string
  status: 'active' | 'inactive' | 'archived'
  branch?: Branch
}

export interface Project {
  id: string
  company_id: string
  branch_id?: string | null
  department_id?: string | null
  lead_membership_id?: string | null
  code: string
  name: string
  description?: string | null
  status: 'planned' | 'active' | 'on_hold' | 'completed' | 'cancelled' | 'archived'
  progress_percent: number
  start_date?: string | null
  due_date?: string | null
  branch?: Branch
  department?: Department
  lead?: Membership
  tasks_count?: number
  completed_tasks_count?: number
}

export interface TaskComment {
  id: string
  task_id: string
  author_membership_id: string
  body: string
  created_at: string
  author?: {
    user?: User
  }
}

export interface TaskStatusHistory {
  id: string
  task_id: string
  from_status: string | null
  to_status: string
  note?: string | null
  created_at: string
  actor?: {
    user?: User
  }
}

export interface Task {
  id: string
  company_id: string
  branch_id?: string | null
  department_id?: string | null
  project_id?: string | null
  creator_membership_id: string
  assignee_membership_id?: string | null
  code: string
  title: string
  description?: string | null
  priority: 'low' | 'normal' | 'high' | 'urgent'
  status: 'backlog' | 'todo' | 'in_progress' | 'blocked' | 'done' | 'cancelled'
  due_at?: string | null
  completed_at?: string | null
  archived_at?: string | null
  created_at: string
  branch?: Branch
  department?: Department
  project?: Project
  assignee?: Membership
  creator?: Membership
  comments?: TaskComment[]
  status_history?: TaskStatusHistory[]
}

export interface Alert {
  id: string
  company_id: string
  branch_id?: string | null
  department_id?: string | null
  project_id?: string | null
  task_id?: string | null
  source: string
  severity: 'info' | 'warning' | 'critical'
  status: 'open' | 'acknowledged' | 'resolved'
  title: string
  message?: string | null
  created_at: string
  branch?: Branch
  department?: Department
  task?: Task
}

export interface DashboardMetrics {
  company_id: string
  branch_id?: string | null
  department_id?: string | null
  operational_status: 'operational' | 'warning' | 'critical'
  kpis: {
    open_task_count: { key: string; label: string; value: number; unit: string }
    overdue_task_count: { key: string; label: string; value: number; unit: string }
    task_overdue_rate: { key: string; label: string; value: number; unit: string }
    task_completion_rate: { key: string; label: string; value: number; unit: string; window_start?: string; window_end?: string }
    active_projects_count: { key: string; label: string; value: number; unit: string }
    average_project_progress: { key: string; label: string; value: number; unit: string }
  }
  distribution: {
    backlog: number
    todo: number
    in_progress: number
    blocked: number
    done: number
    cancelled: number
  }
  alerts_summary: {
    critical: number
    warning: number
    info: number
    total_active: number
  }
  branches?: Array<{
    id: string
    code: string
    name: string
    operational_status: 'operational' | 'warning' | 'critical'
    overdue_count: number
    critical_alerts: number
  }>
}
