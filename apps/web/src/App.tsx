import React, { useEffect, useState } from 'react'
import { api } from './api/client'
import { AlertsListView } from './components/alerts/AlertsListView'
import { LoginView } from './components/auth/LoginView'
import { BranchListView } from './components/branches/BranchListView'
import { QuickDashboardModal } from './components/dashboard/QuickDashboardModal'
import { Navbar } from './components/Navbar'
import { CreateTaskModal } from './components/tasks/CreateTaskModal'
import { TaskDrawer } from './components/tasks/TaskDrawer'
import { TaskListView } from './components/tasks/TaskListView'
import { WorldCanvas } from './components/world/WorldCanvas'
import { AuthProvider, useAuth } from './context/AuthContext'
import type { Alert, Branch, DashboardMetrics, Department, Project, Task } from './types'

const MainApp: React.FC = () => {
  const { user, company, isLoading: authLoading } = useAuth()
  const [currentTab, setCurrentTab] = useState<'campus' | 'dashboard' | 'branches' | 'tasks' | 'alerts'>('campus')
  const [is2DMode, setIs2DMode] = useState<boolean>(false)

  // Data states
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null)
  const [branches, setBranches] = useState<Branch[]>([])
  const [departments, setDepartments] = useState<Department[]>([])
  const [projects, setProjects] = useState<Project[]>([])
  const [tasks, setTasks] = useState<Task[]>([])
  const [alerts, setAlerts] = useState<Alert[]>([])
  const [dataLoading, setDataLoading] = useState<boolean>(false)

  // Modals & Selection states
  const [selectedBranchId, setSelectedBranchId] = useState<string | null>(null)
  const [selectedTask, setSelectedTask] = useState<Task | null>(null)
  const [showCreateTaskModal, setShowCreateTaskModal] = useState<boolean>(false)

  const loadAllData = async () => {
    if (!company) return
    setDataLoading(true)

    try {
      const [metricsData, branchesData, deptsData, projectsData, tasksData, alertsData] = await Promise.all([
        api.getDashboardMetrics(),
        api.getBranches(),
        api.getDepartments(),
        api.getProjects(),
        api.getTasks({ per_page: 50 }),
        api.getAlerts({ status: 'open' }),
      ])

      setMetrics(metricsData)
      setBranches(branchesData.data || [])
      setDepartments(deptsData.data || [])
      setProjects(projectsData.data || [])
      setTasks(tasksData.data || [])
      setAlerts(alertsData.data || [])
    } catch (e) {
      console.error('Failed to load application data:', e)
    } finally {
      setDataLoading(false)
    }
  }

  useEffect(() => {
    if (user && company) {
      loadAllData()
    }
  }, [user, company?.id])

  if (authLoading) {
    return (
      <div
        style={{
          width: '100vw',
          height: '100vh',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          background: '#061325',
          color: 'var(--cyan)',
        }}
      >
        <div style={{ fontSize: '18px', fontWeight: 700, letterSpacing: '1px', marginBottom: '8px' }}>
          INITIALIZING ORBIS WORKSPACE
        </div>
        <div style={{ fontSize: '12px', color: 'var(--muted)' }}>
          Verifying Sanctum session & tenant context...
        </div>
      </div>
    )
  }

  if (!user) {
    return <LoginView />
  }

  const unreadAlertsCount = alerts.filter((a) => a.status === 'open').length

  const handleBranchSelected = (branchId: string) => {
    setSelectedBranchId(branchId)
    // If not on campus tab, switch to it or open detail
    if (currentTab !== 'campus') {
      setCurrentTab('campus')
    }
  }

  return (
    <div style={{ width: '100vw', height: '100vh', display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
      {/* Top Navbar */}
      <Navbar
        currentTab={currentTab}
        onSelectTab={setCurrentTab}
        unreadAlertsCount={unreadAlertsCount}
      />

      {/* Main View Area */}
      <main style={{ flex: 1, position: 'relative', overflow: 'hidden' }}>
        {currentTab === 'campus' && (
          <WorldCanvas
            branches={branches}
            selectedBranchId={selectedBranchId}
            onSelectBranch={handleBranchSelected}
            is2DMode={is2DMode}
            onToggle2DMode={() => setIs2DMode(!is2DMode)}
          />
        )}

        {currentTab === 'dashboard' && (
          <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto', height: '100%', overflowY: 'auto' }}>
            <QuickDashboardModal
              metrics={metrics}
              isLoading={dataLoading}
              onOpenCreateTask={() => setShowCreateTaskModal(true)}
              onSelectBranch={handleBranchSelected}
              onRefresh={loadAllData}
            />
          </div>
        )}

        {currentTab === 'branches' && (
          <BranchListView
            branches={branches}
            onSelectBranch={handleBranchSelected}
            onRefresh={loadAllData}
            onBranchCreated={loadAllData}
          />
        )}

        {currentTab === 'tasks' && (
          <TaskListView
            tasks={tasks}
            onSelectTask={setSelectedTask}
            onOpenCreateTask={() => setShowCreateTaskModal(true)}
            onRefresh={loadAllData}
            isLoading={dataLoading}
          />
        )}

        {currentTab === 'alerts' && (
          <AlertsListView
            alerts={alerts}
            onRefresh={loadAllData}
            onAlertAcknowledged={loadAllData}
          />
        )}

        {/* Task Detail Drawer */}
        <TaskDrawer
          task={selectedTask}
          onClose={() => setSelectedTask(null)}
          onTaskUpdated={(updatedTask) => {
            setSelectedTask(updatedTask)
            setTasks((prev) => prev.map((t) => (t.id === updatedTask.id ? updatedTask : t)))
            // Refresh metrics in background
            api.getDashboardMetrics().then(setMetrics).catch(() => {})
          }}
        />

        {/* Create Task Modal */}
        {showCreateTaskModal && (
          <CreateTaskModal
            branches={branches}
            departments={departments}
            projects={projects}
            onClose={() => setShowCreateTaskModal(false)}
            onTaskCreated={(newTask) => {
              setTasks((prev) => [newTask, ...prev])
              api.getDashboardMetrics().then(setMetrics).catch(() => {})
            }}
          />
        )}
      </main>
    </div>
  )
}

export const App: React.FC = () => {
  return (
    <AuthProvider>
      <MainApp />
    </AuthProvider>
  )
}

export default App
