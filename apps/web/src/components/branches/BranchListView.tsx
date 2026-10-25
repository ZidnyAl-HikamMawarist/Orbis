import { useState } from 'react'
import {
  MapPin,
  Plus,
  RefreshCw,
  Search,
} from 'lucide-react'
import { api } from '../../api/client'
import type { Branch } from '../../types'

interface BranchListViewProps {
  branches: Branch[]
  onSelectBranch: (branchId: string) => void
  onRefresh: () => void
  onBranchCreated: () => void
}

export const BranchListView: React.FC<BranchListViewProps> = ({
  branches,
  onSelectBranch,
  onRefresh,
  onBranchCreated,
}) => {
  const [search, setSearch] = useState('')
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [newCode, setNewCode] = useState('')
  const [newName, setNewName] = useState('')
  const [newAddress, setNewAddress] = useState('')
  const [newTimezone, setNewTimezone] = useState('Asia/Jakarta')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const filteredBranches = branches.filter(
    (b) =>
      b.name.toLowerCase().includes(search.toLowerCase()) ||
      b.code.toLowerCase().includes(search.toLowerCase())
  )

  const handleCreateBranch = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    setErrorMsg(null)

    try {
      await api.createBranch({
        code: newCode.toUpperCase().trim(),
        name: newName.trim(),
        address: newAddress.trim() || null,
        timezone: newTimezone,
        status: 'active',
      })
      setShowCreateModal(false)
      setNewCode('')
      setNewName('')
      setNewAddress('')
      onBranchCreated()
    } catch (err: any) {
      setErrorMsg(err.message || 'Failed to create branch')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto', overflowY: 'auto', height: 'calc(100vh - 60px)' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h2 style={{ fontSize: '20px', fontWeight: 800 }}>Branches & Physical Facilities</h2>
          <p style={{ color: 'var(--muted)', fontSize: '12.5px', marginTop: '2px' }}>
            Operational branch network across autonomous company divisions
          </p>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <button onClick={onRefresh} style={{ padding: '8px' }}>
            <RefreshCw size={15} />
          </button>
          <button
            onClick={() => setShowCreateModal(true)}
            style={{
              background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)',
              border: 'none',
              color: '#fff',
              fontWeight: 600,
            }}
          >
            <Plus size={15} /> Add Branch
          </button>
        </div>
      </div>

      {/* Filter bar */}
      <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
        <div style={{ position: 'relative', flex: 1, maxWidth: '360px' }}>
          <Search size={15} color="var(--muted)" style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)' }} />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search branches by code or name..."
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
      </div>

      {/* Branch Cards Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' }}>
        {filteredBranches.map((b) => (
          <div
            key={b.id}
            className="glass-card"
            style={{
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'space-between',
              cursor: 'pointer',
            }}
            onClick={() => onSelectBranch(b.id)}
          >
            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '10px' }}>
                <span className="badge badge-blue">{b.code}</span>
                <span className={`badge badge-${b.operational_status || 'operational'}`}>
                  {b.operational_status || 'operational'}
                </span>
              </div>

              <h3 style={{ fontSize: '16px', fontWeight: 700, marginBottom: '6px' }}>{b.name}</h3>

              <div style={{ fontSize: '11.5px', color: 'var(--muted)', display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '12px' }}>
                <MapPin size={13} color="var(--cyan)" />
                {b.address || 'Address not registered'}
              </div>
            </div>

            <div style={{ borderTop: '1px solid rgba(66, 121, 179, 0.2)', paddingTop: '10px', display: 'flex', justifyContent: 'space-between', fontSize: '11.5px', color: 'var(--muted)' }}>
              <span>Timezone: <b style={{ color: '#fff' }}>{b.timezone || 'UTC'}</b></span>
              <span>Departments: <b style={{ color: 'var(--cyan)' }}>{b.departments?.length ?? 0}</b></span>
            </div>
          </div>
        ))}
      </div>

      {/* Create Branch Modal */}
      {showCreateModal && (
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
          <div className="glass-panel" style={{ width: '100%', maxWidth: '440px', padding: '24px' }}>
            <h3 style={{ fontSize: '17px', fontWeight: 800, marginBottom: '16px' }}>Register New Branch</h3>

            {errorMsg && (
              <div style={{ background: 'rgba(255,91,121,0.15)', border: '1px solid rgba(255,91,121,0.4)', borderRadius: '8px', padding: '10px', color: 'var(--red)', fontSize: '12px', marginBottom: '14px' }}>
                {errorMsg}
              </div>
            )}

            <form onSubmit={handleCreateBranch} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>BRANCH CODE</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. BDG-01"
                  value={newCode}
                  onChange={(e) => setNewCode(e.target.value.toUpperCase())}
                  style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px 10px', color: '#fff' }}
                />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>BRANCH NAME</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Bandung Tech Hub"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px 10px', color: '#fff' }}
                />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>ADDRESS</label>
                <input
                  type="text"
                  placeholder="e.g. Jl. Dago No. 12"
                  value={newAddress}
                  onChange={(e) => setNewAddress(e.target.value)}
                  style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px 10px', color: '#fff' }}
                />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '11px', fontWeight: 600, color: 'var(--muted)', marginBottom: '4px' }}>TIMEZONE</label>
                <select
                  value={newTimezone}
                  onChange={(e) => setNewTimezone(e.target.value)}
                  style={{ width: '100%', background: '#0a2240', border: '1px solid var(--line)', borderRadius: '8px', padding: '8px 10px', color: '#fff' }}
                >
                  <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                  <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                  <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                  <option value="UTC">UTC</option>
                </select>
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '8px' }}>
                <button type="button" onClick={() => setShowCreateModal(false)}>Cancel</button>
                <button
                  type="submit"
                  disabled={isSubmitting || !newCode.trim() || !newName.trim()}
                  style={{ background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)', border: 'none', color: '#fff' }}
                >
                  {isSubmitting ? 'Saving...' : 'Create Branch'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
