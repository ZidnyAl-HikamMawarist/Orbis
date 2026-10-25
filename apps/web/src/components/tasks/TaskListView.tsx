import { useState } from 'react'
import {
  Calendar,
  Plus,
  RefreshCw,
  Search,
} from 'lucide-react'
import type { Task } from '../../types'

interface TaskListViewProps {
  tasks: Task[]
  onSelectTask: (task: Task) => void
  onOpenCreateTask: () => void
  onRefresh: () => void
  isLoading?: boolean
}

export const TaskListView: React.FC<TaskListViewProps> = ({
  tasks,
  onSelectTask,
  onOpenCreateTask,
  onRefresh,
}) => {
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [priorityFilter, setPriorityFilter] = useState<string>('all')
  const [search, setSearch] = useState('')

  const filteredTasks = tasks.filter((t) => {
    if (statusFilter !== 'all' && t.status !== statusFilter) return false
    if (priorityFilter !== 'all' && t.priority !== priorityFilter) return false
    if (search && !t.title.toLowerCase().includes(search.toLowerCase()) && !t.code.toLowerCase().includes(search.toLowerCase())) return false
    return true
  })

  return (
    <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto', overflowY: 'auto', height: 'calc(100vh - 60px)' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h2 style={{ fontSize: '20px', fontWeight: 800 }}>Operational Task Pipeline</h2>
          <p style={{ color: 'var(--muted)', fontSize: '12.5px', marginTop: '2px' }}>
            Work items, SLA timelines, and atomic status transitions
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <button onClick={onRefresh} style={{ padding: '8px' }}>
            <RefreshCw size={15} />
          </button>
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
        </div>
      </div>

      {/* Filter Tabs */}
      <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '8px', marginBottom: '14px' }}>
        {['all', 'backlog', 'todo', 'in_progress', 'blocked', 'done', 'cancelled'].map((st) => (
          <button
            key={st}
            onClick={() => setStatusFilter(st)}
            style={{
              padding: '6px 12px',
              fontSize: '12px',
              textTransform: 'capitalize',
              background: statusFilter === st ? 'rgba(34, 98, 170, 0.4)' : '#0a2240',
              borderColor: statusFilter === st ? 'var(--cyan)' : 'var(--line)',
              color: statusFilter === st ? '#fff' : 'var(--muted)',
            }}
          >
            {st.replace('_', ' ')}
          </button>
        ))}
      </div>

      {/* Search & Priority Controls */}
      <div style={{ display: 'flex', gap: '12px', marginBottom: '18px', flexWrap: 'wrap' }}>
        <div style={{ position: 'relative', flex: 1, minWidth: '240px' }}>
          <Search size={15} color="var(--muted)" style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)' }} />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search tasks by title or code..."
            style={{
              width: '100%',
              background: '#0a2240',
              border: '1px solid var(--line)',
              borderRadius: '10px',
              padding: '8px 12px 8px 36px',
              color: '#fff',
              outline: 'none',
            }}
          />
        </div>

        <select
          value={priorityFilter}
          onChange={(e) => setPriorityFilter(e.target.value)}
          style={{
            background: '#0a2240',
            border: '1px solid var(--line)',
            borderRadius: '10px',
            padding: '8px 12px',
            color: '#fff',
            outline: 'none',
          }}
        >
          <option value="all">All Priorities</option>
          <option value="urgent">Urgent</option>
          <option value="high">High</option>
          <option value="normal">Normal</option>
          <option value="low">Low</option>
        </select>
      </div>

      {/* Task List / Table */}
      <div className="glass-panel" style={{ overflow: 'hidden' }}>
        {filteredTasks.length > 0 ? (
          <div style={{ display: 'flex', flexDirection: 'column' }}>
            {filteredTasks.map((t) => {
              const isOverdue = t.due_at && new Date(t.due_at) < new Date() && t.status !== 'done'
              return (
                <div
                  key={t.id}
                  onClick={() => onSelectTask(t)}
                  style={{
                    padding: '14px 18px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    borderBottom: '1px solid rgba(66, 121, 179, 0.2)',
                    cursor: 'pointer',
                    transition: 'background 0.15s ease',
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.background = 'rgba(16, 44, 82, 0.4)')}
                  onMouseLeave={(e) => (e.currentTarget.style.background = 'transparent')}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '14px', flex: 1 }}>
                    <span style={{ fontSize: '11px', fontFamily: 'monospace', fontWeight: 700, color: 'var(--cyan)', minWidth: '70px' }}>
                      {t.code}
                    </span>

                    <div>
                      <div style={{ fontWeight: 600, fontSize: '13.5px', color: '#fff' }}>
                        {t.title}
                      </div>
                      <div style={{ fontSize: '11px', color: 'var(--muted)', display: 'flex', alignItems: 'center', gap: '8px', marginTop: '2px' }}>
                        <span>Project: {t.project?.name || 'General'}</span>
                        <span>• Branch: {t.branch?.name || 'All'}</span>
                      </div>
                    </div>
                  </div>

                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <span className={`badge badge-${t.status === 'done' ? 'operational' : 'blue'}`}>
                      {t.status.toUpperCase()}
                    </span>

                    <span style={{ fontSize: '11.5px', color: isOverdue ? 'var(--red)' : 'var(--muted)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <Calendar size={13} />
                      {t.due_at ? new Date(t.due_at).toLocaleDateString() : 'No Due'}
                    </span>
                  </div>
                </div>
              )
            })}
          </div>
        ) : (
          <div style={{ padding: '40px', textAlign: 'center', color: 'var(--muted)' }}>
            No tasks found matching current filters.
          </div>
        )}
      </div>
    </div>
  )
}
