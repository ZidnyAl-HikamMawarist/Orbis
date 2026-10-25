import React, { useState } from 'react'
import { Plus, X } from 'lucide-react'
import { api } from '../../api/client'
import type { Branch, Department, Project, Task } from '../../types'

interface CreateTaskModalProps {
  branches: Branch[]
  departments: Department[]
  projects: Project[]
  onClose: () => void
  onTaskCreated: (task: Task) => void
}

export const CreateTaskModal: React.FC<CreateTaskModalProps> = ({
  branches,
  departments,
  projects,
  onClose,
  onTaskCreated,
}) => {
  const [title, setTitle] = useState('')
  const [code, setCode] = useState(`TSK-${Math.floor(100 + Math.random() * 900)}`)
  const [description, setDescription] = useState('')
  const [priority, setPriority] = useState<'low' | 'normal' | 'high' | 'urgent'>('normal')
  const [status, setStatus] = useState<any>('todo')
  const [dueDate, setDueDate] = useState('')
  const [branchId, setBranchId] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [projectId, setProjectId] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    setErrorMessage(null)

    try {
      const res = await api.createTask({
        code: code.toUpperCase().trim(),
        title: title.trim(),
        description: description.trim() || null,
        priority,
        status,
        due_at: dueDate ? new Date(dueDate).toISOString() : null,
        branch_id: branchId || null,
        department_id: departmentId || null,
        project_id: projectId || null,
      })

      onTaskCreated(res.task)
      onClose()
    } catch (err: any) {
      setErrorMessage(err.message || 'Failed to create task')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(3, 10, 20, 0.75)',
        backdropFilter: 'blur(6px)',
        zIndex: 90,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px',
      }}
    >
      <div
        className="glass-panel"
        style={{
          width: '100%',
          maxWidth: '520px',
          padding: '24px',
          boxShadow: '0 20px 60px rgba(0,0,0,0.8)',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px' }}>
          <h2 style={{ fontSize: '17px', fontWeight: 800 }}>Create Operational Task</h2>
          <button onClick={onClose} style={{ padding: '6px' }}>
            <X size={18} />
          </button>
        </div>

        {errorMessage && (
          <div
            style={{
              background: 'rgba(255, 91, 121, 0.15)',
              border: '1px solid rgba(255, 91, 121, 0.4)',
              borderRadius: '8px',
              padding: '10px 12px',
              color: 'var(--red)',
              fontSize: '12px',
              marginBottom: '16px',
            }}
          >
            {errorMessage}
          </div>
        )}

        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '12px' }}>
            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                CODE
              </label>
              <input
                type="text"
                required
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                style={{
                  width: '100%',
                  background: '#0a2240',
                  border: '1px solid var(--line)',
                  borderRadius: '8px',
                  padding: '8px 10px',
                  color: '#fff',
                  fontFamily: 'monospace',
                  fontWeight: 600,
                  outline: 'none',
                }}
              />
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                TASK TITLE
              </label>
              <input
                type="text"
                required
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="e.g. Audit POS terminal network"
                style={{
                  width: '100%',
                  background: '#0a2240',
                  border: '1px solid var(--line)',
                  borderRadius: '8px',
                  padding: '8px 10px',
                  color: '#fff',
                  outline: 'none',
                }}
              />
            </div>
          </div>

          <div>
            <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
              DESCRIPTION
            </label>
            <textarea
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Action items, deliverables, and acceptance criteria..."
              style={{
                width: '100%',
                background: '#0a2240',
                border: '1px solid var(--line)',
                borderRadius: '8px',
                padding: '8px 10px',
                color: '#fff',
                outline: 'none',
                resize: 'none',
              }}
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px' }}>
            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                PRIORITY
              </label>
              <select
                value={priority}
                onChange={(e) => setPriority(e.target.value as any)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px', color: '#fff' }}
              >
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                STATUS
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px', color: '#fff' }}
              >
                <option value="backlog">Backlog</option>
                <option value="todo">Todo</option>
                <option value="in_progress">In Progress</option>
              </select>
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                DUE DATE
              </label>
              <input
                type="date"
                value={dueDate}
                onChange={(e) => setDueDate(e.target.value)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '7px 8px', color: '#fff' }}
              />
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px' }}>
            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                BRANCH
              </label>
              <select
                value={branchId}
                onChange={(e) => setBranchId(e.target.value)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px', color: '#fff' }}
              >
                <option value="">(Company-wide)</option>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                DEPARTMENT
              </label>
              <select
                value={departmentId}
                onChange={(e) => setDepartmentId(e.target.value)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px', color: '#fff' }}
              >
                <option value="">(None / General)</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>
                PROJECT
              </label>
              <select
                value={projectId}
                onChange={(e) => setProjectId(e.target.value)}
                style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px', color: '#fff' }}
              >
                <option value="">(None / Standalone)</option>
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '10px' }}>
            <button type="button" onClick={onClose} style={{ padding: '9px 16px' }}>
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !title.trim()}
              style={{
                background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)',
                border: 'none',
                color: '#fff',
                fontWeight: 600,
                padding: '9px 18px',
              }}
            >
              {isSubmitting ? 'Creating...' : <><Plus size={15} /> Create Task</>}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
