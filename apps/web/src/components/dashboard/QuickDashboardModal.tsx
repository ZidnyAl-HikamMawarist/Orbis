import {
  AlertTriangle,
  ArrowUpRight,
  CheckCircle2,
  Download,
  FolderGit2,
  ListTodo,
  Plus,
  RefreshCw,
  X,
} from 'lucide-react'
import { api } from '../../api/client'
import type { DashboardMetrics } from '../../types'

interface QuickDashboardModalProps {
  metrics: DashboardMetrics | null
  isLoading: boolean
  onClose?: () => void
  onOpenCreateTask: () => void
  onSelectBranch: (branchId: string) => void
  onRefresh: () => void
}

export const QuickDashboardModal: React.FC<QuickDashboardModalProps> = ({
  metrics,
  isLoading,
  onClose,
  onOpenCreateTask,
  onSelectBranch,
  onRefresh,
}) => {
  if (!metrics && isLoading) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', color: 'var(--muted)' }}>
        <RefreshCw className="spin" size={24} style={{ marginBottom: '12px' }} />
        <div>Calculating operational metrics from backend...</div>
      </div>
    )
  }

  const kpis = metrics?.kpis
  const dist = metrics?.distribution || { backlog: 0, todo: 0, in_progress: 0, blocked: 0, done: 0, cancelled: 0 }
  const totalTasks = Object.values(dist).reduce((a, b) => a + b, 0)

  return (
    <div
      className="glass-panel"
      style={{
        width: '100%',
        maxHeight: 'calc(100vh - 100px)',
        overflowY: 'auto',
        padding: '24px',
        display: 'flex',
        flexDirection: 'column',
        gap: '20px',
      }}
    >
      {/* Header & Actions */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <h2 style={{ fontSize: '18px', fontWeight: 800 }}>Quick Operations Dashboard</h2>
            <span
              className={`badge badge-${metrics?.operational_status || 'operational'}`}
              style={{ textTransform: 'uppercase' }}
            >
              {metrics?.operational_status || 'OPERATIONAL'}
            </span>
          </div>
          <p style={{ color: 'var(--muted)', fontSize: '12px', marginTop: '2px' }}>
            Live aggregated metrics adhering to KPI semantics matrix
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <button onClick={onRefresh} title="Refresh metrics" style={{ padding: '8px' }}>
            <RefreshCw size={15} />
          </button>

          <a
            href={api.getTasksCsvUrl()}
            download
            style={{ textDecoration: 'none' }}
          >
            <button style={{ background: '#0a2a4f', borderColor: 'var(--line-bright)', color: 'var(--cyan)' }}>
              <Download size={14} /> Export CSV
            </button>
          </a>

          <button
            onClick={onOpenCreateTask}
            style={{
              background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)',
              border: 'none',
              color: '#fff',
              fontWeight: 600,
            }}
          >
            <Plus size={15} /> New Task
          </button>

          {onClose && (
            <button onClick={onClose} style={{ padding: '8px' }}>
              <X size={16} />
            </button>
          )}
        </div>
      </div>

      {/* 4 Primary KPI Cards */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px' }}>
        {/* Open Tasks */}
        <div className="glass-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted)', marginBottom: '8px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 600 }}>OPEN TASKS</span>
            <ListTodo size={16} color="var(--cyan)" />
          </div>
          <div style={{ fontSize: '26px', fontWeight: 800, color: '#fff' }}>
            {kpis?.open_task_count.value ?? 0}
          </div>
          <div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '4px' }}>
            Backlog + Todo + Active + Blocked
          </div>
        </div>

        {/* Overdue Rate */}
        <div className="glass-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted)', marginBottom: '8px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 600 }}>OVERDUE SLA RATE</span>
            <AlertTriangle size={16} color={kpis?.overdue_task_count.value ? 'var(--red)' : 'var(--green)'} />
          </div>
          <div style={{ fontSize: '26px', fontWeight: 800, color: kpis?.overdue_task_count.value ? 'var(--red)' : 'var(--green)' }}>
            {kpis?.task_overdue_rate.value ?? 0}%
          </div>
          <div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '4px' }}>
            {kpis?.overdue_task_count.value ?? 0} tasks past due date
          </div>
        </div>

        {/* Completion Rate */}
        <div className="glass-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted)', marginBottom: '8px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 600 }}>30-DAY VELOCITY</span>
            <CheckCircle2 size={16} color="var(--green)" />
          </div>
          <div style={{ fontSize: '26px', fontWeight: 800, color: '#fff' }}>
            {kpis?.task_completion_rate.value ?? 0}%
          </div>
          <div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '4px' }}>
            Completed vs eligible work in window
          </div>
        </div>

        {/* Active Projects */}
        <div className="glass-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted)', marginBottom: '8px' }}>
            <span style={{ fontSize: '11.5px', fontWeight: 600 }}>ACTIVE PROJECTS</span>
            <FolderGit2 size={16} color="var(--purple)" />
          </div>
          <div style={{ fontSize: '26px', fontWeight: 800, color: '#fff' }}>
            {kpis?.active_projects_count.value ?? 0}
          </div>
          <div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '4px' }}>
            Avg delivery progress: {kpis?.average_project_progress.value ?? 0}%
          </div>
        </div>
      </div>

      {/* Workflow Distribution Bar */}
      <div className="glass-card" style={{ padding: '16px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
          <div style={{ fontWeight: 700, fontSize: '13px' }}>Task Workflow Pipeline Distribution</div>
          <div style={{ fontSize: '11px', color: 'var(--muted)' }}>Total: {totalTasks} items</div>
        </div>

        {/* Stacked Progress Bar */}
        <div style={{ height: '10px', display: 'flex', borderRadius: '99px', overflow: 'hidden', background: '#07162b', marginBottom: '14px' }}>
          {totalTasks > 0 ? (
            <>
              <div style={{ width: `${(dist.backlog / totalTasks) * 100}%`, background: '#64748b' }} title={`Backlog: ${dist.backlog}`} />
              <div style={{ width: `${(dist.todo / totalTasks) * 100}%`, background: 'var(--cyan)' }} title={`Todo: ${dist.todo}`} />
              <div style={{ width: `${(dist.in_progress / totalTasks) * 100}%`, background: 'var(--blue)' }} title={`In Progress: ${dist.in_progress}`} />
              <div style={{ width: `${(dist.blocked / totalTasks) * 100}%`, background: 'var(--gold)' }} title={`Blocked: ${dist.blocked}`} />
              <div style={{ width: `${(dist.done / totalTasks) * 100}%`, background: 'var(--green)' }} title={`Done: ${dist.done}`} />
            </>
          ) : (
            <div style={{ width: '100%', background: '#102a4a' }} />
          )}
        </div>

        {/* Pipeline Legend */}
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '14px', fontSize: '11.5px' }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#64748b' }} /> Backlog: <b>{dist.backlog}</b>
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--cyan)' }} /> Todo: <b>{dist.todo}</b>
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--blue)' }} /> In Progress: <b>{dist.in_progress}</b>
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--gold)' }} /> Blocked: <b>{dist.blocked}</b>
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: 'var(--green)' }} /> Done: <b>{dist.done}</b>
          </span>
        </div>
      </div>

      {/* Branch Health Status Breakdown */}
      <div className="glass-card" style={{ padding: '16px' }}>
        <div style={{ fontWeight: 700, fontSize: '13px', marginBottom: '12px' }}>
          Branch Operational Health Overview
        </div>

        {metrics?.branches && metrics.branches.length > 0 ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '10px' }}>
            {metrics.branches.map((b) => (
              <div
                key={b.id}
                onClick={() => onSelectBranch(b.id)}
                style={{
                  padding: '12px',
                  borderRadius: '10px',
                  background: 'rgba(10, 32, 61, 0.5)',
                  border: '1px solid var(--line)',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  transition: 'all 0.15s ease',
                }}
              >
                <div>
                  <div style={{ fontWeight: 700, fontSize: '13px' }}>{b.name}</div>
                  <div style={{ fontSize: '11px', color: 'var(--muted)' }}>Code: {b.code}</div>
                  <div style={{ fontSize: '11px', color: b.overdue_count > 0 ? 'var(--red)' : 'var(--muted)', marginTop: '2px' }}>
                    {b.overdue_count} Overdue tasks
                  </div>
                </div>

                <div style={{ textAlign: 'right' }}>
                  <span className={`badge badge-${b.operational_status}`}>
                    {b.operational_status}
                  </span>
                  <div style={{ color: 'var(--cyan)', marginTop: '6px' }}>
                    <ArrowUpRight size={14} />
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div style={{ color: 'var(--muted)', fontSize: '12px', textAlign: 'center', padding: '16px' }}>
            No branches registered for this company yet.
          </div>
        )}
      </div>
    </div>
  )
}
