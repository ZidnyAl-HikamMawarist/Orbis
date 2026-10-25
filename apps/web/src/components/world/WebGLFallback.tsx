import React from 'react'
import { AlertCircle, Layers, MapPin } from 'lucide-react'
import type { Branch } from '../../types'

interface WebGLFallbackProps {
  branches: Branch[]
  selectedBranchId: string | null
  onSelectBranch: (branchId: string) => void
  errorReason?: string | null
}

export const WebGLFallback: React.FC<WebGLFallbackProps> = ({
  branches,
  selectedBranchId,
  onSelectBranch,
  errorReason,
}) => {
  return (
    <div
      style={{
        width: '100%',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '30px',
        background: 'radial-gradient(circle at 50% 40%, #0d294d 0%, #061325 80%)',
        overflowY: 'auto',
      }}
    >
      {/* Notice header if WebGL failed */}
      {errorReason && (
        <div
          style={{
            maxWidth: '600px',
            background: 'rgba(245, 179, 66, 0.15)',
            border: '1px solid rgba(245, 179, 66, 0.4)',
            borderRadius: '12px',
            padding: '12px 16px',
            marginBottom: '24px',
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            color: 'var(--gold)',
          }}
        >
          <AlertCircle size={20} style={{ flexShrink: 0 }} />
          <div>
            <div style={{ fontWeight: 700, fontSize: '13px' }}>2D Interactive Campus View Active</div>
            <div style={{ fontSize: '11.5px', color: 'var(--muted)', marginTop: '2px' }}>
              {errorReason}. You have full access to operational workflows, branch exploration, and task management.
            </div>
          </div>
        </div>
      )}

      {/* 2D Topological Map Card */}
      <div
        className="glass-panel"
        style={{
          width: '100%',
          maxWidth: '850px',
          padding: '24px',
          display: 'flex',
          flexDirection: 'column',
          gap: '20px',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid var(--line)', paddingBottom: '14px' }}>
          <div>
            <h3 style={{ fontSize: '16px', fontWeight: 800, display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Layers size={18} color="var(--cyan)" />
              Topological Campus Network
            </h3>
            <p style={{ color: 'var(--muted)', fontSize: '12px', marginTop: '2px' }}>
              Click any branch or department node to inspect operational metrics
            </p>
          </div>
          <span className="badge badge-blue">
            {branches.length} Active Branches
          </span>
        </div>

        {/* Branch Grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '14px' }}>
          {branches.map((b) => {
            const isSelected = selectedBranchId === b.id
            const statusColor =
              b.operational_status === 'critical'
                ? 'var(--red)'
                : b.operational_status === 'warning'
                ? 'var(--gold)'
                : 'var(--green)'

            return (
              <div
                key={b.id}
                onClick={() => onSelectBranch(b.id)}
                className="glass-card"
                style={{
                  cursor: 'pointer',
                  borderColor: isSelected ? 'var(--cyan)' : 'rgba(66, 121, 179, 0.3)',
                  boxShadow: isSelected ? '0 0 20px rgba(53, 216, 255, 0.3)' : 'none',
                  background: isSelected ? 'rgba(34, 98, 170, 0.35)' : 'rgba(16, 44, 82, 0.6)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <MapPin size={16} color="var(--cyan)" />
                    <span style={{ fontWeight: 700, fontSize: '14px' }}>{b.name}</span>
                  </div>
                  <span
                    style={{
                      width: '10px',
                      height: '10px',
                      borderRadius: '50%',
                      background: statusColor,
                      boxShadow: `0 0 8px ${statusColor}`,
                    }}
                  />
                </div>

                <div style={{ fontSize: '11px', color: 'var(--muted)', marginBottom: '12px' }}>
                  Code: <b style={{ color: '#fff' }}>{b.code}</b> | TZ: {b.timezone || 'UTC'}
                </div>

                {/* Departments sub-pills */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                  {b.departments && b.departments.length > 0 ? (
                    b.departments.map((d) => (
                      <span
                        key={d.id}
                        style={{
                          fontSize: '10.5px',
                          background: '#0c2647',
                          border: '1px solid var(--line)',
                          padding: '2px 8px',
                          borderRadius: '6px',
                          color: '#bed4ee',
                        }}
                      >
                        {d.name}
                      </span>
                    ))
                  ) : (
                    <span style={{ fontSize: '10.5px', color: 'var(--muted)' }}>
                      0 sub-departments
                    </span>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
