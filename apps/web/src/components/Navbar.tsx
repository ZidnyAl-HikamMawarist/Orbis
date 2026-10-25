import React, { useState } from 'react'
import {
  Building2,
  ChevronDown,
  Layers,
  LayoutDashboard,
  LogOut,
  MapPin,
  Menu,
  X,
  Bell,
  CheckSquare,
  Globe2,
} from 'lucide-react'
import { useAuth } from '../context/AuthContext'

interface NavbarProps {
  currentTab: 'campus' | 'dashboard' | 'branches' | 'tasks' | 'alerts'
  onSelectTab: (tab: 'campus' | 'dashboard' | 'branches' | 'tasks' | 'alerts') => void
  unreadAlertsCount: number
}

export const Navbar: React.FC<NavbarProps> = ({
  currentTab,
  onSelectTab,
  unreadAlertsCount,
}) => {
  const { user, company, role, memberships, switchCompany, logout } = useAuth()
  const [showCompanyMenu, setShowCompanyMenu] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  return (
    <header className="topbar" style={{
      height: '60px',
      display: 'flex',
      alignItems: 'center',
      gap: '16px',
      padding: '0 16px',
      background: 'linear-gradient(180deg, #081c38 0%, #06152b 100%)',
      borderBottom: '1px solid var(--line)',
      position: 'relative',
      zIndex: 50,
      overflowX: 'auto',
      maxWidth: '100vw',
      scrollbarWidth: 'none',
    }}>
      {/* Brand */}
      <div
        className="brand"
        onClick={() => onSelectTab('campus')}
        style={{ display: 'flex', alignItems: 'center', gap: '12px', cursor: 'pointer', flexShrink: 0 }}
      >
        <div
          style={{
            width: '36px',
            height: '36px',
            borderRadius: '10px',
            display: 'grid',
            placeItems: 'center',
            background: 'linear-gradient(135deg, #27d7ff 0%, #3a6df0 55%, #6c47ff 100%)',
            boxShadow: '0 0 18px rgba(53,216,255,0.4)',
          }}
        >
          <Layers size={20} color="#fff" />
        </div>
        <div>
          <div style={{ fontWeight: 800, fontSize: '18px', letterSpacing: '0.6px', lineHeight: 1.1 }}>
            ORBIS
          </div>
          <div style={{ fontSize: '10px', color: 'var(--muted)', fontWeight: 500 }}>
            BUSINESS INTELLIGENCE
          </div>
        </div>
      </div>

      {/* Desktop Navigation */}
      <nav style={{ display: 'flex', alignItems: 'center', gap: '6px', marginLeft: '12px', flexShrink: 0 }} className="desktop-nav">
        <button
          onClick={() => onSelectTab('campus')}
          style={{
            background: currentTab === 'campus' ? 'rgba(34, 98, 170, 0.4)' : 'transparent',
            borderColor: currentTab === 'campus' ? 'var(--cyan)' : 'transparent',
            color: currentTab === 'campus' ? '#fff' : 'var(--muted)',
            flexShrink: 0,
            whiteSpace: 'nowrap',
          }}
        >
          <Globe2 size={16} /> 3D Campus
        </button>

        <button
          onClick={() => onSelectTab('dashboard')}
          style={{
            background: currentTab === 'dashboard' ? 'rgba(34, 98, 170, 0.4)' : 'transparent',
            borderColor: currentTab === 'dashboard' ? 'var(--cyan)' : 'transparent',
            color: currentTab === 'dashboard' ? '#fff' : 'var(--muted)',
            flexShrink: 0,
            whiteSpace: 'nowrap',
          }}
        >
          <LayoutDashboard size={16} /> Quick Dashboard
        </button>

        <button
          onClick={() => onSelectTab('branches')}
          style={{
            background: currentTab === 'branches' ? 'rgba(34, 98, 170, 0.4)' : 'transparent',
            borderColor: currentTab === 'branches' ? 'var(--cyan)' : 'transparent',
            color: currentTab === 'branches' ? '#fff' : 'var(--muted)',
            flexShrink: 0,
            whiteSpace: 'nowrap',
          }}
        >
          <MapPin size={16} /> Branches
        </button>

        <button
          onClick={() => onSelectTab('tasks')}
          style={{
            background: currentTab === 'tasks' ? 'rgba(34, 98, 170, 0.4)' : 'transparent',
            borderColor: currentTab === 'tasks' ? 'var(--cyan)' : 'transparent',
            color: currentTab === 'tasks' ? '#fff' : 'var(--muted)',
            flexShrink: 0,
            whiteSpace: 'nowrap',
          }}
        >
          <CheckSquare size={16} /> Tasks
        </button>

        <button
          onClick={() => onSelectTab('alerts')}
          style={{
            background: currentTab === 'alerts' ? 'rgba(34, 98, 170, 0.4)' : 'transparent',
            borderColor: currentTab === 'alerts' ? 'var(--cyan)' : 'transparent',
            color: currentTab === 'alerts' ? '#fff' : 'var(--muted)',
            position: 'relative',
            flexShrink: 0,
            whiteSpace: 'nowrap',
          }}
        >
          <Bell size={16} /> Alerts
          {unreadAlertsCount > 0 && (
            <span
              style={{
                marginLeft: '4px',
                background: 'var(--red)',
                color: '#fff',
                fontSize: '10px',
                fontWeight: 700,
                padding: '1px 6px',
                borderRadius: '99px',
                boxShadow: '0 0 8px rgba(255,91,121,0.6)',
              }}
            >
              {unreadAlertsCount}
            </span>
          )}
        </button>
      </nav>

      {/* Right Controls: Company Switcher & Profile */}
      <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '12px', flexShrink: 0 }}>
        {/* Company Switcher Dropdown */}
        <div style={{ position: 'relative' }}>
          <button
            onClick={() => setShowCompanyMenu(!showCompanyMenu)}
            style={{
              background: '#0a2240',
              borderColor: 'var(--line)',
              padding: '6px 12px',
              borderRadius: '8px',
            }}
          >
            <Building2 size={15} color="var(--cyan)" />
            <span style={{ fontWeight: 600 }}>{company?.name || 'Select Company'}</span>
            <ChevronDown size={14} color="var(--muted)" />
          </button>

          {showCompanyMenu && (
            <div
              style={{
                position: 'absolute',
                top: 'calc(100% + 6px)',
                right: 0,
                width: '240px',
                background: '#0a213f',
                border: '1px solid var(--line-bright)',
                borderRadius: '12px',
                boxShadow: '0 12px 32px rgba(0,0,0,0.7)',
                overflow: 'hidden',
                zIndex: 60,
              }}
            >
              <div style={{ padding: '8px 12px', fontSize: '11px', color: 'var(--muted)', borderBottom: '1px solid var(--line)' }}>
                SWITCH COMPANY TENANT
              </div>
              {memberships.map((m) => (
                <div
                  key={m.id}
                  onClick={() => {
                    if (m.company_id !== company?.id) {
                      switchCompany(m.company_id)
                    }
                    setShowCompanyMenu(false)
                  }}
                  style={{
                    padding: '10px 14px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    cursor: 'pointer',
                    background: m.company_id === company?.id ? 'rgba(53, 216, 255, 0.15)' : 'transparent',
                    borderBottom: '1px solid rgba(66, 121, 179, 0.2)',
                  }}
                >
                  <div>
                    <div style={{ fontWeight: 600, color: m.company_id === company?.id ? 'var(--cyan)' : '#fff' }}>
                      {m.company?.name || m.company_id}
                    </div>
                    <div style={{ fontSize: '10px', color: 'var(--muted)' }}>Role: {m.role}</div>
                  </div>
                  {m.company_id === company?.id && (
                    <span style={{ fontSize: '10px', color: 'var(--cyan)', fontWeight: 700 }}>ACTIVE</span>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* User Info & Logout */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <div
            style={{
              padding: '4px 10px',
              borderRadius: '99px',
              background: '#0b2240',
              border: '1px solid var(--line)',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
            }}
          >
            <div
              style={{
                width: '26px',
                height: '26px',
                borderRadius: '50%',
                background: 'linear-gradient(135deg, #ffd29d, #f09565)',
                display: 'grid',
                placeItems: 'center',
                fontWeight: 700,
                color: '#061325',
                fontSize: '12px',
              }}
            >
              {user?.name?.[0]?.toUpperCase() || 'U'}
            </div>
            <div>
              <div style={{ fontSize: '12px', fontWeight: 600 }}>{user?.name}</div>
              <div style={{ fontSize: '10px', color: 'var(--muted)' }}>{role}</div>
            </div>
          </div>

          <button
            onClick={logout}
            title="Log out"
            style={{
              background: 'transparent',
              borderColor: 'var(--line)',
              padding: '7px 10px',
              borderRadius: '8px',
            }}
          >
            <LogOut size={16} color="var(--red)" />
          </button>
        </div>

        {/* Mobile menu toggle */}
        <button
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          className="mobile-menu-btn"
          style={{ display: 'none', padding: '6px 10px' }}
        >
          {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
        </button>
      </div>
    </header>
  )
}
