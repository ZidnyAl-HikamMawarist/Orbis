import { test, expect } from '@playwright/test'

async function loginAsAdmin(page: any) {
  await page.goto('/')
  await page.locator('input[type="email"]').fill('admin@nusantara.local')
  await page.locator('input[type="password"]').fill('password')
  await page.getByRole('button', { name: /Sign In to Workspace/i }).click()
  await expect(page.getByText('Nusantara Retail Group', { exact: false })).toBeVisible({ timeout: 15000 })
}

test.describe('ORBIS End-to-End Autonomous Mission Journeys', () => {
  test('Journey A & B — Authentication, Session Lifecycle, and Company Tenant Context', async ({ page }) => {
    // 1. Visit root - verify unauthenticated login screen
    await page.goto('/')
    await expect(page.getByText('ORBIS', { exact: false })).toBeVisible()
    await expect(page.getByText('Business Intelligence & 3D Operations SaaS')).toBeVisible()
    await expect(page.getByRole('button', { name: /Sign In to Workspace/i })).toBeVisible()

    // 2. Perform direct login
    await loginAsAdmin(page)

    // 3. Confirm real user identity
    await expect(page.getByText('Budi Santoso', { exact: false })).toBeVisible()

    // 4. Test page reload persistence (session maintained via stateful cookie)
    await page.reload()
    await expect(page.getByText('Nusantara Retail Group', { exact: false })).toBeVisible({ timeout: 15000 })
    await expect(page.getByText('Budi Santoso', { exact: false })).toBeVisible()

    // 5. Test logout
    await page.getByTitle('Log out').click()
    await expect(page.getByRole('button', { name: /Sign In to Workspace/i })).toBeVisible({ timeout: 20000 })
  })

  test('Journey C & D — Branches, Task Creation, Drawer Details, and Transitions', async ({ page }) => {
    // 1. Login
    await loginAsAdmin(page)

    // 2. Navigate to Branches tab
    await page.getByRole('button', { name: /Branches/i }).click()
    await expect(page.getByText('Branches & Physical Facilities')).toBeVisible()

    // 3. Navigate to Tasks tab
    await page.getByRole('button', { name: /Tasks/i }).click()
    await expect(page.getByText('Operational Task Pipeline')).toBeVisible()

    // 4. Open Create Task Modal and create a new task
    await page.getByRole('button', { name: /New Task/i }).first().click()
    await expect(page.getByText('Create Operational Task')).toBeVisible()

    const randomSuffix = Math.floor(1000 + Math.random() * 9000)
    const taskTitle = `E2E Automated Task ${randomSuffix}`

    await page.fill('input[placeholder*="Audit POS terminal"]', taskTitle)
    await page.getByRole('button', { name: /Create Task/i }).click()

    // 5. Verify newly created task appears in list
    await expect(page.getByText(taskTitle)).toBeVisible({ timeout: 10000 })

    // 6. Click task to open slide-out TaskDrawer
    await page.getByText(taskTitle).click()
    await expect(page.getByText('WORKFLOW STATE TRANSITION')).toBeVisible()
    await expect(page.getByText('COMMENTS & DISCUSSION')).toBeVisible()

    // 7. Transition status from TODO to IN_PROGRESS
    await page.selectOption('select:has(option[value="in_progress"])', 'in_progress')
    await page.getByRole('button', { name: /Transition/i }).click()
    await expect(page.getByText('IN_PROGRESS').first()).toBeVisible({ timeout: 10000 })

    // 8. Add comment
    await page.fill('input[placeholder*="Add an operational note"]', 'Verified automated execution in Chromium E2E')
    await page.locator('form:has(input[placeholder*="Add an operational note"]) button[type="submit"]').click()
    await expect(page.getByText('Verified automated execution in Chromium E2E')).toBeVisible({ timeout: 10000 })

    // 9. Close drawer
    await page.locator('button:has(svg.lucide-x)').first().click()
  })

  test('Journey E & H — Quick Dashboard Metrics and 3D World / 2D Fallback', async ({ page }) => {
    // 1. Login
    await loginAsAdmin(page)

    // 2. Open Quick Dashboard
    await page.getByRole('button', { name: /Quick Dashboard/i }).click()
    await expect(page.getByText('Quick Operations Dashboard')).toBeVisible()
    await expect(page.getByText('OPEN TASKS')).toBeVisible()
    await expect(page.getByText('OVERDUE SLA RATE')).toBeVisible()
    await expect(page.getByText('30-DAY VELOCITY')).toBeVisible()
    await expect(page.getByText('ACTIVE PROJECTS')).toBeVisible()

    // 3. Switch to 3D Campus
    await page.getByRole('button', { name: /3D Campus/i }).click()

    // 4. Test 2D Fallback Toggle button in HUD
    const toggle2DBtn = page.getByTitle('Switch to 2D Map')
    if (await toggle2DBtn.isVisible()) {
      await toggle2DBtn.click()
      await expect(page.getByText('Topological Campus Network')).toBeVisible()
    }
  })
})
