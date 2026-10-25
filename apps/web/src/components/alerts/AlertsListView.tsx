import { useState } from 'react'
import {
  AlertCircle,
  AlertTriangle,
  Info,
  RefreshCw,
} from 'lucide-react'
import { api } from '../../api/client'
import type { Alert } from '../../types'

interface AlertsListViewProps {
  alerts: Alert[]
  onRefresh: () => void
  onAlertAcknowledged: () => void
}

export const AlertsListView: React.FC<AlertsListViewProps> = ({
  alerts,
  onRefresh,
  onAlertAcknowledged,
}) => {
  const [severityFilter, setSeverityFilter] = useState('all')
  const [isProcessing, setIsProcessing] = useState<string | null>(null)

  const filteredAlerts = alerts.filter(
    (a) => severityFilter === 'all' || a.severity === severityFilter
  )

  const handleAcknowledge = async (id: string) => {
    setIsProcessing(id)
    try {
      await api.acknowledgeAlert(id)
      onAlertAcknowledged()
    } finally {
      setIsProcessing(null)
    }
  }

  return (
    <div style={{ padding: '24px', maxWidth: '1000px', margin: '0 auto', overflowY: 'auto', height: 'calc(100vh - 60px)' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px' }}>
        <div>
          <h2 style={{ fontSize: '20px', fontWeight: 800 }}>System & Operational Alerts</h2>
          <p style={{ color: 'var(--muted)', fontSize: '12.5px', marginTop: '2px' }}>
            Automated notifications, threshold breaches, and SLA compliance monitoring
          </p>
        </div>

        <button onClick={onRefresh} style={{ padding: '8px' }}>
          <RefreshCw size={15} />
        </button>
      </div>

      {/* Severity Filter Tabs */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '18px' }}>
        {['all', 'critical', 'warning', 'info'].map((sev) => (
          <button
            key={sev}
            onClick={() => setSeverityFilter(sev)}
            style={{
              padding: '6px 14px',
              fontSize: '12px',
              textTransform: 'capitalize',
              background: severityFilter === sev ? 'rgba(34, 98, 170, 0.4)' : '#0a2240',
              borderColor: severityFilter === sev ? 'var(--cyan)' : 'var(--line)',
              color: severityFilter === sev ? '#fff' : 'var(--muted)',
            }}
          >
            {sev}
          </button>
        ))}
      </div>

      {/* Alerts Feed */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
        {filteredAlerts.length > 0 ? (
          filteredAlerts.map((a) => {
            const isCrit = a.severity === 'critical'
            const isWarn = a.severity === 'warning'
            const Icon = isCrit ? AlertCircle : isWarn ? AlertTriangle : Info
            const color = isCrit ? 'var(--red)' : isWarn ? 'var(--gold)' : 'var(--cyan)'

            return (
              <div
                key={a.id}
                className="glass-card"
                style={{
                  display: 'flex',
                  alignItems: 'flex-start',
                  justifyContent: 'space-between',
                  gap: '16px',
                  borderLeft: `4px solid ${color}`,
                }}
              >
                <div style={{ display: 'flex', gap: '12px', flex: 1 }}>
                  <Icon size={20} color={color} style={{ marginTop: '2px', flexShrink: 0 }} />
                  <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                      <span style={{ fontWeight: 700, fontSize: '14px', color: '#fff' }}>
                        {a.title}
                      </span>
                      <span className={`badge badge-${a.severity}`}>
                        {a.severity.toUpperCase()}
                      </span>
                      <span style={{ fontSize: '11px', color: 'var(--muted)' }}>
                        Status: {a.status}
                      </span>
                    </div>

                    <div style={{ fontSize: '12.5px', color: '#bed4ee', lineHeight: 1.4, marginBottom: '6px' }}>
                      {a.message || 'No additional details logged.'}
                    </div>

                    <div style={{ fontSize: '11px', color: 'var(--muted)' }}>
                      Branch: {a.branch?.name || 'All'} • Source: {a.source} • {new Date(a.created_at).toLocaleString()}
                    </div>
                  </div>
                </div>

                {a.status === 'open' && (
                  <button
                    onClick={() => handleAcknowledge(a.id)}
                    disabled={isProcessing === a.id}
                    style={{
                      background: '#092544',
                      borderColor: 'var(--line-bright)',
                      color: 'var(--cyan)',
                      fontSize: '11.5px',
                      padding: '6px 12px',
                    }}
                  >
                    {isProcessing === a.id ? 'Saving...' : 'Acknowledge'}
                  </button>
                )}
              </div>
            )
          })
        ) : (
          <div style={{ padding: '40px', textAlign: 'center', color: 'var(--muted)' }}>
            No active alerts matching current filters.
          </div>
        )}
      </div>
    </div>
  )
}
