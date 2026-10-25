import React, { createContext, useContext, useEffect, useState } from 'react'
import { api, ApiError } from '../api/client'
import type { Company, Membership, User } from '../types'

interface AuthContextType {
  user: User | null
  company: Company | null
  role: string | null
  memberships: Membership[]
  isLoading: boolean
  error: string | null
  login: (credentials: { email: string; password: string }) => Promise<void>
  logout: () => Promise<void>
  switchCompany: (companyId: string) => Promise<void>
  refreshSession: () => Promise<void>
  clearError: () => void
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [company, setCompany] = useState<Company | null>(null)
  const [role, setRole] = useState<string | null>(null)
  const [memberships, setMemberships] = useState<Membership[]>([])
  const [isLoading, setIsLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const refreshSession = async () => {
    try {
      setIsLoading(true)
      const data = await api.getMe()
      setUser(data.user)
      setCompany(data.current_company)
      setRole(data.current_membership?.role || null)

      if (data.companies) {
        setMemberships(
          data.companies.map((c) => ({
            id: c.id,
            company_id: c.id,
            user_id: data.user.id,
            role: c.role as any,
            status: 'active',
            company: {
              id: c.id,
              name: c.name,
              slug: c.slug,
              status: 'active',
            },
          }))
        )
      }
      setError(null)
    } catch {
      setUser(null)
      setCompany(null)
      setRole(null)
      setMemberships([])
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    refreshSession()
  }, [])

  const login = async (credentials: { email: string; password: string }) => {
    try {
      setIsLoading(true)
      setError(null)
      await api.initCsrf()
      const data = await api.login(credentials)
      setUser(data.user)
      setCompany(data.current_company)
      setRole(data.current_membership?.role || null)

      if (data.companies) {
        setMemberships(
          data.companies.map((c) => ({
            id: c.id,
            company_id: c.id,
            user_id: data.user.id,
            role: c.role as any,
            status: 'active',
            company: {
              id: c.id,
              name: c.name,
              slug: c.slug,
              status: 'active',
            },
          }))
        )
      }
    } catch (err: any) {
      if (err instanceof ApiError) {
        setError(err.message)
      } else {
        setError('Login failed. Please check your credentials.')
      }
      throw err
    } finally {
      setIsLoading(false)
    }
  }

  const logout = async () => {
    try {
      await api.logout()
    } finally {
      setUser(null)
      setCompany(null)
      setRole(null)
      setMemberships([])
    }
  }

  const switchCompany = async (companyId: string) => {
    try {
      setIsLoading(true)
      setError(null)
      const data = await api.switchCompany(companyId)
      setCompany(data.current_company)
      setRole(data.current_membership?.role || null)
      await refreshSession()
    } catch (err: any) {
      setError(err instanceof ApiError ? err.message : 'Failed to switch company')
      throw err
    } finally {
      setIsLoading(false)
    }
  }

  const clearError = () => setError(null)

  return (
    <AuthContext.Provider
      value={{
        user,
        company,
        role,
        memberships,
        isLoading,
        error,
        login,
        logout,
        switchCompany,
        refreshSession,
        clearError,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
