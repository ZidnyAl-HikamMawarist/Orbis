import React, { useState } from 'react'
import { AlertCircle, ArrowRight, Layers, Lock, Mail } from 'lucide-react'
import { useAuth } from '../../context/AuthContext'

export const LoginView: React.FC = () => {
  const { login, error, clearError } = useAuth()
  const [email, setEmail] = useState('admin@nusantara.local')
  const [password, setPassword] = useState('password')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    clearError()
    try {
      await login({ email, password })
    } catch {
      // Error handled in context
    } finally {
      setIsSubmitting(false)
    }
  }

  const fillTestAccount = (testEmail: string) => {
    setEmail(testEmail)
    setPassword('password')
    clearError()
  }

  return (
    <div
      style={{
        width: '100vw',
        height: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'radial-gradient(circle at 50% 30%, #0d294d 0%, #061325 70%, #030a14 100%)',
        padding: '20px',
      }}
    >
      <div
        className="glass-panel"
        style={{
          width: '100%',
          maxWidth: '420px',
          padding: '36px',
          boxShadow: '0 20px 60px rgba(0,0,0,0.8), 0 0 40px rgba(53,216,255,0.15)',
        }}
      >
        {/* Logo & Header */}
        <div style={{ textAlign: 'center', marginBottom: '28px' }}>
          <div
            style={{
              width: '54px',
              height: '54px',
              borderRadius: '14px',
              margin: '0 auto 16px',
              display: 'grid',
              placeItems: 'center',
              background: 'linear-gradient(135deg, #27d7ff 0%, #3a6df0 55%, #6c47ff 100%)',
              boxShadow: '0 0 24px rgba(53,216,255,0.5)',
            }}
          >
            <Layers size={30} color="#fff" />
          </div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '0.5px', marginBottom: '6px' }}>
            ORBIS
          </h1>
          <p style={{ color: 'var(--muted)', fontSize: '13px' }}>
            Business Intelligence & 3D Operations SaaS
          </p>
        </div>

        {/* Error Alert */}
        {error && (
          <div
            style={{
              background: 'rgba(255, 91, 121, 0.15)',
              border: '1px solid rgba(255, 91, 121, 0.4)',
              borderRadius: '10px',
              padding: '10px 14px',
              marginBottom: '20px',
              display: 'flex',
              alignItems: 'center',
              gap: '10px',
              color: 'var(--red)',
              fontSize: '12.5px',
            }}
          >
            <AlertCircle size={18} style={{ flexShrink: 0 }} />
            <span>{error}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          <div>
            <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, marginBottom: '6px', color: 'var(--muted)' }}>
              EMAIL ADDRESS
            </label>
            <div style={{ position: 'relative' }}>
              <Mail size={16} color="var(--muted)" style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)' }} />
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@company.com"
                style={{
                  width: '100%',
                  background: '#0a2240',
                  border: '1px solid var(--line)',
                  borderRadius: '10px',
                  padding: '10px 12px 10px 38px',
                  color: '#fff',
                  outline: 'none',
                }}
              />
            </div>
          </div>

          <div>
            <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, marginBottom: '6px', color: 'var(--muted)' }}>
              PASSWORD
            </label>
            <div style={{ position: 'relative' }}>
              <Lock size={16} color="var(--muted)" style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)' }} />
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                style={{
                  width: '100%',
                  background: '#0a2240',
                  border: '1px solid var(--line)',
                  borderRadius: '10px',
                  padding: '10px 12px 10px 38px',
                  color: '#fff',
                  outline: 'none',
                }}
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            style={{
              marginTop: '8px',
              padding: '12px',
              borderRadius: '10px',
              background: 'linear-gradient(135deg, #27d7ff 0%, #2575fc 100%)',
              border: 'none',
              color: '#fff',
              fontWeight: 700,
              fontSize: '14px',
              boxShadow: '0 0 20px rgba(53,216,255,0.4)',
            }}
          >
            {isSubmitting ? 'Authenticating...' : (
              <>Sign In to Workspace <ArrowRight size={16} /></>
            )}
          </button>
        </form>

        {/* Demo Seed Accounts Quick Select */}
        <div style={{ marginTop: '24px', paddingTop: '20px', borderTop: '1px solid var(--line)' }}>
          <div style={{ fontSize: '11px', color: 'var(--muted)', fontWeight: 600, marginBottom: '10px', textAlign: 'center' }}>
            DEMO SEED ACCOUNTS
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
            <button
              type="button"
              onClick={() => fillTestAccount('admin@nusantara.local')}
              style={{ padding: '6px 10px', fontSize: '11.5px', justifyContent: 'space-between', background: 'rgba(16, 44, 82, 0.4)' }}
            >
              <span>Nusantara Owner</span>
              <span style={{ color: 'var(--cyan)' }}>admin@nusantara.local</span>
            </button>
            <button
              type="button"
              onClick={() => fillTestAccount('manager@nusantara.local')}
              style={{ padding: '6px 10px', fontSize: '11.5px', justifyContent: 'space-between', background: 'rgba(16, 44, 82, 0.4)' }}
            >
              <span>Nusantara Ops Manager</span>
              <span style={{ color: 'var(--cyan)' }}>manager@nusantara.local</span>
            </button>
            <button
              type="button"
              onClick={() => fillTestAccount('owner@borneo.local')}
              style={{ padding: '6px 10px', fontSize: '11.5px', justifyContent: 'space-between', background: 'rgba(16, 44, 82, 0.4)' }}
            >
              <span>Borneo Owner (Tenant 2)</span>
              <span style={{ color: 'var(--cyan)' }}>owner@borneo.local</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
