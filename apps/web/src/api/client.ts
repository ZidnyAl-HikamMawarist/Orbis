import type {
  Alert,
  Branch,
  DashboardMetrics,
  Department,
  Membership,
  Project,
  Task,
  User,
} from '../types'

const API_BASE = '/api/v1'

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(
    message: string,
    status: number,
    errors?: Record<string, string[]>
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers || {})
  headers.set('Accept', 'application/json')
  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }

  // Extract XSRF token from document.cookie if available
  const xsrfCookie = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
  if (xsrfCookie) {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfCookie.split('=')[1]))
  }

  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
    credentials: 'include',
  })

  if (response.status === 204) {
    return {} as T
  }

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(
      data.message || `Request failed with status ${response.status}`,
      response.status,
      data.errors
    )
  }

  return data as T
}

export const api = {
  // Auth
  async initCsrf(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  },

  async login(credentials: { email: string; password: string }): Promise<{ user: User; company: any; memberships: Membership[] }> {
    return request('/auth/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    })
  },

  async getMe(): Promise<{ user: User; active_company: any; memberships: Membership[] }> {
    return request('/auth/me')
  },

  async switchCompany(company_id: string): Promise<{ message: string; active_company: any; active_role: string }> {
    return request('/auth/switch-company', {
      method: 'POST',
      body: JSON.stringify({ company_id }),
    })
  },

  async logout(): Promise<void> {
    await request('/auth/logout', { method: 'POST' })
  },

  // Dashboard
  async getDashboardMetrics(params?: { branch_id?: string; department_id?: string }): Promise<DashboardMetrics> {
    const query = new URLSearchParams()
    if (params?.branch_id) query.set('branch_id', params.branch_id)
    if (params?.department_id) query.set('department_id', params.department_id)
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/dashboard/metrics${queryString}`)
  },

  // Branches
  async getBranches(params?: { search?: string; status?: string }): Promise<{ data: Branch[] }> {
    const query = new URLSearchParams()
    if (params?.search) query.set('search', params.search)
    if (params?.status) query.set('status', params.status)
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/branches${queryString}`)
  },

  async getBranch(id: string): Promise<{ branch: Branch }> {
    return request(`/branches/${id}`)
  },

  async createBranch(payload: Partial<Branch>): Promise<{ branch: Branch }> {
    return request('/branches', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  // Departments
  async getDepartments(params?: { branch_id?: string; search?: string }): Promise<{ data: Department[] }> {
    const query = new URLSearchParams()
    if (params?.branch_id) query.set('branch_id', params.branch_id)
    if (params?.search) query.set('search', params.search)
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/departments${queryString}`)
  },

  // Projects
  async getProjects(params?: { branch_id?: string; status?: string; search?: string }): Promise<{ data: Project[] }> {
    const query = new URLSearchParams()
    if (params?.branch_id) query.set('branch_id', params.branch_id)
    if (params?.status) query.set('status', params.status)
    if (params?.search) query.set('search', params.search)
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/projects${queryString}`)
  },

  async createProject(payload: Partial<Project>): Promise<{ project: Project }> {
    return request('/projects', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  // Tasks
  async getTasks(params?: {
    project_id?: string
    branch_id?: string
    department_id?: string
    status?: string
    priority?: string
    search?: string
    per_page?: number
  }): Promise<{ data: Task[]; total: number }> {
    const query = new URLSearchParams()
    if (params?.project_id) query.set('project_id', params.project_id)
    if (params?.branch_id) query.set('branch_id', params.branch_id)
    if (params?.department_id) query.set('department_id', params.department_id)
    if (params?.status) query.set('status', params.status)
    if (params?.priority) query.set('priority', params.priority)
    if (params?.search) query.set('search', params.search)
    if (params?.per_page) query.set('per_page', params.per_page.toString())
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/tasks${queryString}`)
  },

  async getTask(id: string): Promise<{ task: Task }> {
    return request(`/tasks/${id}`)
  },

  async createTask(payload: Partial<Task>): Promise<{ task: Task }> {
    return request('/tasks', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  async transitionTask(id: string, status: string, note?: string): Promise<{ task: Task }> {
    return request(`/tasks/${id}/transition`, {
      method: 'POST',
      body: JSON.stringify({ status, note }),
    })
  },

  async addTaskComment(id: string, body: string): Promise<{ comment: any }> {
    return request(`/tasks/${id}/comments`, {
      method: 'POST',
      body: JSON.stringify({ body }),
    })
  },

  // Alerts
  async getAlerts(params?: { severity?: string; status?: string }): Promise<{ data: Alert[] }> {
    const query = new URLSearchParams()
    if (params?.severity) query.set('severity', params.severity)
    if (params?.status) query.set('status', params.status)
    const queryString = query.toString() ? `?${query.toString()}` : ''
    return request(`/alerts${queryString}`)
  },

  async acknowledgeAlert(id: string): Promise<{ alert: Alert }> {
    return request(`/alerts/${id}/acknowledge`, { method: 'POST' })
  },

  // Reports
  getTasksCsvUrl(): string {
    return '/api/v1/reports/tasks-csv'
  },
}
