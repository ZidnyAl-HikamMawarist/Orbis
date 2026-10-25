import { useState } from 'react'
import {
  History,
  MessageSquare,
  Send,
  X,
} from 'lucide-react'
import { api } from '../../api/client'
import { useAuth } from '../../context/AuthContext'
import type { Task } from '../../types'

interface TaskDrawerProps {
  task: Task | null
  onClose: () => void
  onTaskUpdated: (updatedTask: Task) => void
}

export const TaskDrawer: React.FC<TaskDrawerProps> = ({
  task,
  onClose,
  onTaskUpdated,
}) => {
  const { role } = useAuth()
  const [commentBody, setCommentBody] = useState('')
  const [isSubmittingComment, setIsSubmittingComment] = useState(false)
  const [transitionStatus, setTransitionStatus] = useState<string>('')
  const [transitionNote, setTransitionNote] = useState('')
  const [isTransitioning, setIsTransitioning] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  if (!task) return null

  const handleStatusTransition = async () => {
    if (!transitionStatus || transitionStatus === task.status) return
    setIsTransitioning(true)
    setErrorMessage(null)

    try {
      const res = await api.transitionTask(task.id, transitionStatus, transitionNote || undefined)
      onTaskUpdated(res.task)
      setTransitionNote('')
      setTransitionStatus('')
    } catch (err: any) {
      setErrorMessage(err.message || 'Failed to update task status')
    } finally {
      setIsTransitioning(false)
    }
  }

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!commentBody.trim()) return
    setIsSubmittingComment(true)
    setErrorMessage(null)

    try {
      await api.addTaskComment(task.id, commentBody.trim())
      setCommentBody('')
      const refreshed = await api.getTask(task.id)
      onTaskUpdated(refreshed.task)
    } catch (err: any) {
      setErrorMessage(err.message || 'Failed to add comment')
    } finally {
      setIsSubmittingComment(false)
    }
  }

  const isCompleted = task.status === 'done'
  const canReopen = ['owner', 'company_admin', 'operations_manager', 'branch_manager'].includes(role || '')

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        right: 0,
        bottom: 0,
        width: '100%',
        maxWidth: '480px',
        background: '#07182f',
        borderLeft: '1px solid var(--line-bright)',
        boxShadow: '-10px 0 40px rgba(0,0,0,0.8)',
        zIndex: 80,
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      {/* Header */}
      <div
        style={{
          padding: '20px',
          borderBottom: '1px solid var(--line)',
          display: 'flex',
          alignItems: 'flex-start',
          justifyContent: 'space-between',
          background: '#091f3b',
        }}
      >
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
            <span style={{ fontSize: '11px', fontWeight: 700, color: 'var(--cyan)' }}>
              {task.code}
            </span>
            <span className={`badge badge-${task.status === 'done' ? 'operational' : 'blue'}`}>
              {task.status.toUpperCase()}
            </span>
            <span style={{ fontSize: '11px', color: 'var(--muted)', textTransform: 'capitalize' }}>
              • {task.priority} Priority
            </span>
          </div>
          <h2 style={{ fontSize: '16px', fontWeight: 800, lineHeight: 1.3 }}>
            {task.title}
          </h2>
        </div>

        <button onClick={onClose} style={{ padding: '6px' }}>
          <X size={18} />
        </button>
      </div>

      {/* Body Content */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '20px', display: 'flex', flexDirection: 'column', gap: '20px' }}>
        {errorMessage && (
          <div
            style={{
              background: 'rgba(255, 91, 121, 0.15)',
              border: '1px solid rgba(255, 91, 121, 0.4)',
              borderRadius: '8px',
              padding: '10px 12px',
              color: 'var(--red)',
              fontSize: '12px',
            }}
          >
            {errorMessage}
          </div>
        )}

        {/* Task Description */}
        <div>
          <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--muted)', marginBottom: '6px' }}>
            DESCRIPTION
          </div>
          <div style={{ fontSize: '13px', color: '#bed4ee', lineHeight: 1.5, background: '#0a2240', padding: '12px', borderRadius: '8px', border: '1px solid var(--line)' }}>
            {task.description || 'No description provided for this task.'}
          </div>
        </div>

        {/* Context metadata */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', fontSize: '12px' }}>
          <div style={{ background: '#0b2444', padding: '10px', borderRadius: '8px' }}>
            <div style={{ color: 'var(--muted)', fontSize: '10.5px' }}>BRANCH</div>
            <div style={{ fontWeight: 600, marginTop: '2px' }}>{task.branch?.name || 'Global'}</div>
          </div>
          <div style={{ background: '#0b2444', padding: '10px', borderRadius: '8px' }}>
            <div style={{ color: 'var(--muted)', fontSize: '10.5px' }}>DEPARTMENT</div>
            <div style={{ fontWeight: 600, marginTop: '2px' }}>{task.department?.name || 'All'}</div>
          </div>
          <div style={{ background: '#0b2444', padding: '10px', borderRadius: '8px' }}>
            <div style={{ color: 'var(--muted)', fontSize: '10.5px' }}>PROJECT</div>
            <div style={{ fontWeight: 600, marginTop: '2px' }}>{task.project?.name || 'None'}</div>
          </div>
          <div style={{ background: '#0b2444', padding: '10px', borderRadius: '8px' }}>
            <div style={{ color: 'var(--muted)', fontSize: '10.5px' }}>DUE DATE</div>
            <div style={{ fontWeight: 600, marginTop: '2px', color: task.due_at && new Date(task.due_at) < new Date() && task.status !== 'done' ? 'var(--red)' : '#fff' }}>
              {task.due_at ? new Date(task.due_at).toLocaleDateString() : 'No due date'}
            </div>
          </div>
        </div>

        {/* Status Transition Control */}
        <div className="glass-card" style={{ padding: '14px' }}>
          <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--muted)', marginBottom: '10px' }}>
            WORKFLOW STATE TRANSITION
          </div>

          <div style={{ display: 'flex', gap: '8px', marginBottom: '8px' }}>
            <select
              value={transitionStatus}
              onChange={(e) => setTransitionStatus(e.target.value)}
              style={{
                flex: 1,
                background: '#091c36',
                border: '1px solid var(--line)',
                borderRadius: '8px',
                padding: '8px',
                color: '#fff',
                outline: 'none',
              }}
            >
              <option value="">Select target status...</option>
              <option value="backlog">Backlog</option>
              <option value="todo">Todo</option>
              <option value="in_progress">In Progress</option>
              <option value="blocked">Blocked</option>
              <option value="done">Done</option>
              <option value="cancelled">Cancelled</option>
            </select>

            <button
              onClick={handleStatusTransition}
              disabled={!transitionStatus || isTransitioning || (isCompleted && !canReopen && transitionStatus !== 'done')}
              style={{
                background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)',
                border: 'none',
                color: '#fff',
                fontWeight: 600,
              }}
            >
              {isTransitioning ? 'Updating...' : 'Transition'}
            </button>
          </div>

          {isCompleted && !canReopen && (
            <div style={{ fontSize: '11px', color: 'var(--gold)', marginTop: '4px' }}>
              Note: Reopening a completed task requires Manager or Admin authorization.
            </div>
          )}

          <input
            type="text"
            value={transitionNote}
            onChange={(e) => setTransitionNote(e.target.value)}
            placeholder="Optional transition note for audit history..."
            style={{
              width: '100%',
              background: '#091c36',
              border: '1px solid var(--line)',
              borderRadius: '8px',
              padding: '8px 10px',
              color: '#fff',
              fontSize: '12px',
              outline: 'none',
              marginTop: '6px',
            }}
          />
        </div>

        {/* Status History Timeline */}
        {task.status_history && task.status_history.length > 0 && (
          <div>
            <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--muted)', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
              <History size={14} /> STATUS TIMELINE
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {task.status_history.map((hist) => (
                <div
                  key={hist.id}
                  style={{
                    background: '#091d36',
                    padding: '8px 12px',
                    borderRadius: '8px',
                    borderLeft: '3px solid var(--cyan)',
                    fontSize: '11.5px',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--muted)' }}>
                    <span>{hist.actor?.user?.name || 'Member'}</span>
                    <span>{new Date(hist.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                  </div>
                  <div style={{ fontWeight: 600, marginTop: '2px', color: '#fff' }}>
                    {hist.from_status || 'Initial'} → {hist.to_status}
                  </div>
                  {hist.note && (
                    <div style={{ color: 'var(--muted)', fontStyle: 'italic', marginTop: '2px' }}>
                      "{hist.note}"
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Comments Section */}
        <div>
          <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--muted)', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <MessageSquare size={14} /> COMMENTS & DISCUSSION
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '12px' }}>
            {task.comments && task.comments.length > 0 ? (
              task.comments.map((comm) => (
                <div
                  key={comm.id}
                  style={{
                    background: '#091d36',
                    padding: '10px 12px',
                    borderRadius: '8px',
                    border: '1px solid rgba(66, 121, 179, 0.2)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '11px', color: 'var(--muted)', marginBottom: '4px' }}>
                    <span style={{ fontWeight: 600, color: 'var(--cyan)' }}>
                      {comm.author?.user?.name || 'Team Member'}
                    </span>
                    <span>{new Date(comm.created_at).toLocaleDateString()}</span>
                  </div>
                  <div style={{ fontSize: '12.5px', color: '#bed4ee' }}>{comm.body}</div>
                </div>
              ))
            ) : (
              <div style={{ color: 'var(--muted)', fontSize: '11.5px', fontStyle: 'italic' }}>
                No comments yet. Start the conversation below.
              </div>
            )}
          </div>

          {/* Add Comment Form */}
          <form onSubmit={handleAddComment} style={{ display: 'flex', gap: '8px' }}>
            <input
              type="text"
              value={commentBody}
              onChange={(e) => setCommentBody(e.target.value)}
              placeholder="Add an operational note or comment..."
              style={{
                flex: 1,
                background: '#091c36',
                border: '1px solid var(--line)',
                borderRadius: '8px',
                padding: '8px 12px',
                color: '#fff',
                fontSize: '12px',
                outline: 'none',
              }}
            />
            <button
              type="submit"
              disabled={isSubmittingComment || !commentBody.trim()}
              style={{ background: '#0d2d52', borderColor: 'var(--line-bright)', color: 'var(--cyan)' }}
            >
              <Send size={14} />
            </button>
          </form>
        </div>
      </div>
    </div>
  )
}
