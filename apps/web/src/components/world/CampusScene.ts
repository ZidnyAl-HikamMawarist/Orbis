import * as THREE from 'three'
import type { Branch } from '../../types'

export interface CampusSceneCallbacks {
  onBranchClick: (branchId: string) => void
  onBranchHover: (branch: Branch | null, screenPos: { x: number; y: number } | null) => void
}

export class CampusScene {
  private renderer: THREE.WebGLRenderer | null = null
  private scene: THREE.Scene
  private camera: THREE.PerspectiveCamera
  private animFrameId: number | null = null
  private container: HTMLCanvasElement
  private branches: Branch[] = []
  private callbacks: CampusSceneCallbacks
  private interactiveMeshes: Map<THREE.Mesh, Branch> = new Map()
  private raycaster = new THREE.Raycaster()
  private mouse = new THREE.Vector2(-999, -999)

  // Camera Orbit Controls
  private isDragging = false
  private prevMousePos = { x: 0, y: 0 }
  private cameraAngle = { theta: 0.8, phi: 0.6 }
  private cameraDistance = 45
  private cameraTarget = new THREE.Vector3(0, 0, 0)

  constructor(canvas: HTMLCanvasElement, callbacks: CampusSceneCallbacks) {
    this.container = canvas
    this.callbacks = callbacks
    this.scene = new THREE.Scene()
    this.scene.background = new THREE.Color(0x061325)
    this.scene.fog = new THREE.FogExp2(0x061325, 0.015)

    const width = canvas.clientWidth || window.innerWidth
    const height = canvas.clientHeight || window.innerHeight

    this.camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 500)
    this.updateCameraPosition()
  }

  public init(): boolean {
    try {
      this.renderer = new THREE.WebGLRenderer({
        canvas: this.container,
        antialias: true,
        powerPreference: 'default',
      })
      this.renderer.setSize(this.container.clientWidth, this.container.clientHeight)
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
      this.renderer.shadowMap.enabled = true
      this.renderer.shadowMap.type = THREE.PCFSoftShadowMap

      this.setupLighting()
      this.setupEnvironment()
      this.attachEventListeners()
      this.startAnimationLoop()
      return true
    } catch (e) {
      console.error('WebGL initialization failed:', e)
      return false
    }
  }

  private setupLighting(): void {
    const ambientLight = new THREE.AmbientLight(0x7fa2d1, 1.2)
    this.scene.add(ambientLight)

    const dirLight = new THREE.DirectionalLight(0xffffff, 2.0)
    dirLight.position.set(30, 50, 30)
    dirLight.castShadow = true
    dirLight.shadow.mapSize.width = 1024
    dirLight.shadow.mapSize.height = 1024
    this.scene.add(dirLight)

    const cyanPointLight = new THREE.PointLight(0x35d8ff, 3.5, 60)
    cyanPointLight.position.set(0, 15, 0)
    this.scene.add(cyanPointLight)
  }

  private setupEnvironment(): void {
    // Central Island Platform (Hexagonal / Cylinder)
    const islandGeo = new THREE.CylinderGeometry(14, 16, 3, 32)
    const islandMat = new THREE.MeshStandardMaterial({
      color: 0x0a2240,
      roughness: 0.6,
      metalness: 0.2,
    })
    const island = new THREE.Mesh(islandGeo, islandMat)
    island.position.y = -1.5
    island.receiveShadow = true
    this.scene.add(island)

    // Glowing Neon Edge Ring
    const ringGeo = new THREE.TorusGeometry(14.2, 0.2, 16, 64)
    const ringMat = new THREE.MeshBasicMaterial({ color: 0x35d8ff })
    const ring = new THREE.Mesh(ringGeo, ringMat)
    ring.rotation.x = Math.PI / 2
    ring.position.y = 0.05
    this.scene.add(ring)

    // Central Headquarters Tower
    const hqGeo = new THREE.BoxGeometry(6, 12, 6)
    const hqMat = new THREE.MeshStandardMaterial({
      color: 0x102f54,
      metalness: 0.4,
      roughness: 0.3,
    })
    const hq = new THREE.Mesh(hqGeo, hqMat)
    hq.position.y = 6
    hq.castShadow = true
    hq.receiveShadow = true
    this.scene.add(hq)

    // Grid Floor under island
    const grid = new THREE.GridHelper(100, 50, 0x1f4775, 0x0c2444)
    grid.position.y = -10
    this.scene.add(grid)
  }

  public updateData(branches: Branch[]): void {
    this.branches = branches

    // Clear old branch interactive meshes
    this.interactiveMeshes.forEach((_, mesh) => {
      this.scene.remove(mesh)
      mesh.geometry.dispose()
      if (Array.isArray(mesh.material)) {
        mesh.material.forEach((m) => m.dispose())
      } else {
        mesh.material.dispose()
      }
    })
    this.interactiveMeshes.clear()

    // Spawn Branch Island Pavilions in a circular orbit around HQ
    const total = branches.length
    if (total === 0) return

    const radius = 22
    branches.forEach((branch, i) => {
      const angle = (i / total) * Math.PI * 2
      const x = Math.cos(angle) * radius
      const z = Math.sin(angle) * radius

      // Small island base for each branch
      const branchBaseGeo = new THREE.CylinderGeometry(4.5, 5, 2, 16)
      const branchBaseMat = new THREE.MeshStandardMaterial({
        color: 0x0c2545,
        roughness: 0.7,
      })
      const branchBase = new THREE.Mesh(branchBaseGeo, branchBaseMat)
      branchBase.position.set(x, -1, z)
      branchBase.receiveShadow = true
      this.scene.add(branchBase)

      // Pavilion Tower
      const statusColor =
        branch.operational_status === 'critical'
          ? 0xff5b79
          : branch.operational_status === 'warning'
          ? 0xf5b342
          : 0x2ce0a3

      const towerGeo = new THREE.BoxGeometry(3.5, 7, 3.5)
      const towerMat = new THREE.MeshStandardMaterial({
        color: 0x183c66,
        roughness: 0.4,
        metalness: 0.3,
      })
      const tower = new THREE.Mesh(towerGeo, towerMat)
      tower.position.set(x, 3.5, z)
      tower.castShadow = true
      tower.receiveShadow = true
      this.scene.add(tower)

      // Glowing Beacon on top of tower
      const beaconGeo = new THREE.SphereGeometry(1.2, 16, 16)
      const beaconMat = new THREE.MeshStandardMaterial({
        color: statusColor,
        emissive: statusColor,
        emissiveIntensity: 0.9,
      })
      const beacon = new THREE.Mesh(beaconGeo, beaconMat)
      beacon.position.set(x, 8.5, z)
      this.scene.add(beacon)

      // Register tower and beacon for click/hover raycasting
      this.interactiveMeshes.set(tower, branch)
      this.interactiveMeshes.set(beacon, branch)
    })
  }

  private updateCameraPosition(): void {
    const x = this.cameraTarget.x + this.cameraDistance * Math.sin(this.cameraAngle.theta) * Math.cos(this.cameraAngle.phi)
    const y = this.cameraTarget.y + this.cameraDistance * Math.sin(this.cameraAngle.phi)
    const z = this.cameraTarget.z + this.cameraDistance * Math.cos(this.cameraAngle.theta) * Math.cos(this.cameraAngle.phi)
    this.camera.position.set(x, Math.max(y, 10), z)
    this.camera.lookAt(this.cameraTarget)
  }

  public zoom(delta: number): void {
    this.cameraDistance = THREE.MathUtils.clamp(this.cameraDistance + delta, 15, 100)
    this.updateCameraPosition()
  }

  public resetCamera(): void {
    this.cameraAngle = { theta: 0.8, phi: 0.6 }
    this.cameraDistance = 45
    this.cameraTarget.set(0, 0, 0)
    this.updateCameraPosition()
  }

  public focusBranch(branchId: string): void {
    const branch = this.branches.find((b) => b.id === branchId)
    if (!branch) return

    for (const [mesh, b] of this.interactiveMeshes.entries()) {
      if (b.id === branchId) {
        this.cameraTarget.copy(mesh.position)
        this.cameraDistance = 25
        this.updateCameraPosition()
        break
      }
    }
  }

  private onPointerDown = (e: MouseEvent | TouchEvent) => {
    this.isDragging = true
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY
    this.prevMousePos = { x: clientX, y: clientY }
  }

  private onPointerMove = (e: MouseEvent | TouchEvent) => {
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY

    if (this.isDragging) {
      const dx = clientX - this.prevMousePos.x
      const dy = clientY - this.prevMousePos.y
      this.cameraAngle.theta -= dx * 0.008
      this.cameraAngle.phi = THREE.MathUtils.clamp(this.cameraAngle.phi + dy * 0.008, 0.2, 1.4)
      this.prevMousePos = { x: clientX, y: clientY }
      this.updateCameraPosition()
    }

    // Raycast hover detection (mouse only)
    if (!('touches' in e)) {
      const rect = this.container.getBoundingClientRect()
      this.mouse.x = ((clientX - rect.left) / rect.width) * 2 - 1
      this.mouse.y = -((clientY - rect.top) / rect.height) * 2 + 1

      this.raycaster.setFromCamera(this.mouse, this.camera)
      const intersects = this.raycaster.intersectObjects(Array.from(this.interactiveMeshes.keys()))

      if (intersects.length > 0) {
        const hit = intersects[0].object as THREE.Mesh
        const branch = this.interactiveMeshes.get(hit)
        this.callbacks.onBranchHover(branch || null, { x: clientX, y: clientY })
        this.container.style.cursor = 'pointer'
      } else {
        this.callbacks.onBranchHover(null, null)
        this.container.style.cursor = 'default'
      }
    }
  }

  private onPointerUp = (e: MouseEvent | TouchEvent) => {
    if (this.isDragging) {
      this.isDragging = false
    }

    // Check click hit
    if ('clientX' in e) {
      const rect = this.container.getBoundingClientRect()
      const x = ((e.clientX - rect.left) / rect.width) * 2 - 1
      const y = -((e.clientY - rect.top) / rect.height) * 2 + 1

      this.raycaster.setFromCamera(new THREE.Vector2(x, y), this.camera)
      const intersects = this.raycaster.intersectObjects(Array.from(this.interactiveMeshes.keys()))

      if (intersects.length > 0) {
        const hit = intersects[0].object as THREE.Mesh
        const branch = this.interactiveMeshes.get(hit)
        if (branch) {
          this.callbacks.onBranchClick(branch.id)
        }
      }
    }
  }

  private onWheel = (e: WheelEvent) => {
    e.preventDefault()
    this.zoom(e.deltaY * 0.03)
  }

  public resize(width: number, height: number): void {
    if (!this.renderer) return
    this.camera.aspect = width / height
    this.camera.updateProjectionMatrix()
    this.renderer.setSize(width, height)
  }

  private attachEventListeners(): void {
    this.container.addEventListener('mousedown', this.onPointerDown)
    window.addEventListener('mousemove', this.onPointerMove)
    window.addEventListener('mouseup', this.onPointerUp)
    this.container.addEventListener('wheel', this.onWheel, { passive: false })

    this.container.addEventListener('touchstart', this.onPointerDown, { passive: true })
    window.addEventListener('touchmove', this.onPointerMove, { passive: true })
    window.addEventListener('touchend', this.onPointerUp, { passive: true })
  }

  private startAnimationLoop(): void {
    const animate = () => {
      this.animFrameId = requestAnimationFrame(animate)

      // Gentle beacon rotation
      this.scene.children.forEach((child) => {
        if (child instanceof THREE.Mesh && child.geometry instanceof THREE.SphereGeometry) {
          child.rotation.y += 0.015
        }
      })

      if (this.renderer) {
        this.renderer.render(this.scene, this.camera)
      }
    }
    animate()
  }

  public destroy(): void {
    if (this.animFrameId !== null) {
      cancelAnimationFrame(this.animFrameId)
      this.animFrameId = null
    }

    this.container.removeEventListener('mousedown', this.onPointerDown)
    window.removeEventListener('mousemove', this.onPointerMove)
    window.removeEventListener('mouseup', this.onPointerUp)
    this.container.removeEventListener('wheel', this.onWheel)

    this.container.removeEventListener('touchstart', this.onPointerDown)
    window.removeEventListener('touchmove', this.onPointerMove)
    window.removeEventListener('touchend', this.onPointerUp)

    this.interactiveMeshes.forEach((_, mesh) => {
      mesh.geometry.dispose()
      if (Array.isArray(mesh.material)) {
        mesh.material.forEach((m) => m.dispose())
      } else {
        mesh.material.dispose()
      }
    })
    this.interactiveMeshes.clear()

    if (this.renderer) {
      this.renderer.dispose()
      this.renderer = null
    }
  }
}
