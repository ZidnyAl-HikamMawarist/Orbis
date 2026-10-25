import { useEffect, useRef, useState } from 'react'
import {
  Compass,
  MapPin,
  RotateCcw,
  ZoomIn,
  ZoomOut,
} from 'lucide-react'
import type { Branch } from '../../types'
import { CampusScene } from './CampusScene'
import { WebGLFallback } from './WebGLFallback'

interface WorldCanvasProps {
  branches: Branch[]
  selectedBranchId: string | null
  onSelectBranch: (branchId: string) => void
  is2DMode: boolean
  onToggle2DMode: () => void
}

export const WorldCanvas: React.FC<WorldCanvasProps> = ({
  branches,
  selectedBranchId,
  onSelectBranch,
  is2DMode,
  onToggle2DMode,
}) => {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const sceneRef = useRef<CampusScene | null>(null)
  const [webGlAvailable, setWebGlAvailable] = useState<boolean>(true)
  const [webGlError, setWebGlError] = useState<string | null>(null)
  const [hoveredBranch, setHoveredBranch] = useState<Branch | null>(null)
  const [tooltipPos, setTooltipPos] = useState<{ x: number; y: number } | null>(null)

  useEffect(() => {
    if (is2DMode || !canvasRef.current) return

    const canvas = canvasRef.current
    const scene = new CampusScene(canvas, {
      onBranchClick: (branchId) => {
        onSelectBranch(branchId)
      },
      onBranchHover: (branch, screenPos) => {
        setHoveredBranch(branch)
        setTooltipPos(screenPos)
      },
    })

    const success = scene.init()
    if (!success) {
      setWebGlAvailable(false)
      setWebGlError('WebGL hardware acceleration is unavailable in this environment')
      return
    }

    scene.updateData(branches)
    sceneRef.current = scene

    const handleResize = () => {
      if (canvasRef.current && sceneRef.current) {
        sceneRef.current.resize(canvasRef.current.clientWidth, canvasRef.current.clientHeight)
      }
    }
    window.addEventListener('resize', handleResize)

    return () => {
      window.removeEventListener('resize', handleResize)
      scene.destroy()
      sceneRef.current = null
    }
  }, [is2DMode])

  // Update scene data when branches change
  useEffect(() => {
    if (sceneRef.current) {
      sceneRef.current.updateData(branches)
    }
  }, [branches])

  // Focus branch if selected
  useEffect(() => {
    if (selectedBranchId && sceneRef.current) {
      sceneRef.current.focusBranch(selectedBranchId)
    }
  }, [selectedBranchId])

  if (is2DMode || !webGlAvailable) {
    return (
      <WebGLFallback
        branches={branches}
        selectedBranchId={selectedBranchId}
        onSelectBranch={onSelectBranch}
        errorReason={webGlError}
      />
    )
  }

  return (
    <div style={{ position: 'relative', width: '100%', height: '100%', overflow: 'hidden' }}>
      <canvas
        ref={canvasRef}
        style={{ width: '100%', height: '100%', display: 'block' }}
      />

      {/* Floating HUD Controls */}
      <div
        className="glass-panel"
        style={{
          position: 'absolute',
          bottom: '24px',
          right: '24px',
          display: 'flex',
          flexDirection: 'column',
          gap: '8px',
          padding: '8px',
          borderRadius: '12px',
          zIndex: 30,
        }}
      >
        <button
          onClick={() => sceneRef.current?.zoom(-5)}
          title="Zoom In"
          style={{ padding: '8px' }}
        >
          <ZoomIn size={16} />
        </button>

        <button
          onClick={() => sceneRef.current?.zoom(5)}
          title="Zoom Out"
          style={{ padding: '8px' }}
        >
          <ZoomOut size={16} />
        </button>

        <button
          onClick={() => sceneRef.current?.resetCamera()}
          title="Reset Camera Angle"
          style={{ padding: '8px' }}
        >
          <RotateCcw size={16} />
        </button>

        <button
          onClick={onToggle2DMode}
          title="Switch to 2D Map"
          style={{ padding: '8px', borderColor: 'var(--cyan)' }}
        >
          <Compass size={16} color="var(--cyan)" />
        </button>
      </div>

      {/* Hover Tooltip Pill */}
      {hoveredBranch && tooltipPos && (
        <div
          style={{
            position: 'fixed',
            left: `${tooltipPos.x + 14}px`,
            top: `${tooltipPos.y - 36}px`,
            pointerEvents: 'none',
            zIndex: 100,
            background: 'rgba(8, 26, 51, 0.95)',
            border: '1px solid var(--line-bright)',
            borderRadius: '8px',
            padding: '6px 12px',
            boxShadow: '0 8px 24px rgba(0,0,0,0.6)',
          }}
        >
          <div style={{ fontWeight: 700, fontSize: '12.5px', color: '#fff', display: 'flex', alignItems: 'center', gap: '6px' }}>
            <MapPin size={13} color="var(--cyan)" />
            {hoveredBranch.name} ({hoveredBranch.code})
          </div>
          <div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '2px' }}>
            Click to open branch details & tasks
          </div>
        </div>
      )}

      {/* Camera Instruction Hint */}
      <div
        style={{
          position: 'absolute',
          bottom: '24px',
          left: '24px',
          fontSize: '11px',
          color: 'var(--muted)',
          background: 'rgba(6, 19, 37, 0.7)',
          padding: '6px 12px',
          borderRadius: '8px',
          border: '1px solid rgba(66, 121, 179, 0.25)',
          pointerEvents: 'none',
        }}
      >
        Left-drag to Orbit • Scroll to Zoom • Click pavilion to explore
      </div>
    </div>
  )
}
