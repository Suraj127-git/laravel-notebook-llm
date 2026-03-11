import { AnimatePresence, motion } from 'framer-motion'
import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { useGenerateAudioOverviewMutation, useGetAudioOverviewQuery } from '../../store/api/audioApi'
import type { AudioOverviewUpdate } from '../../hooks/useDocumentChannel'

type Props = {
  notebookId: number
  audioReadySignal: AudioOverviewUpdate | null
}

const TERMINAL_STATUSES = new Set(['ready', 'failed'])

// ── Web Speech API player ─────────────────────────────────────────────────────
function useScriptPlayer(script: string | null) {
  const [playing, setPlaying] = useState(false)
  const [progress, setProgress] = useState(0) // 0–100
  const uttRef = useRef<SpeechSynthesisUtterance | null>(null)
  const turnsRef = useRef<string[]>([])
  const indexRef = useRef(0)

  const parseTurns = useCallback((raw: string) => {
    return raw.split('\n').map((l) => l.trim()).filter(Boolean)
  }, [])

  const speakNext = useCallback(() => {
    const turns = turnsRef.current
    if (indexRef.current >= turns.length) {
      setPlaying(false)
      setProgress(100)
      return
    }
    const text = turns[indexRef.current].replace(/^(Alex|Sam):\s*/i, '')
    const utt = new SpeechSynthesisUtterance(text)
    utt.rate = 1.05
    utt.onend = () => {
      indexRef.current++
      setProgress(Math.round((indexRef.current / turns.length) * 100))
      speakNext()
    }
    uttRef.current = utt
    window.speechSynthesis.speak(utt)
  }, [])

  const play = useCallback(() => {
    if (!script) return
    window.speechSynthesis.cancel()
    turnsRef.current = parseTurns(script)
    indexRef.current = 0
    setProgress(0)
    setPlaying(true)
    speakNext()
  }, [script, parseTurns, speakNext])

  const pause = useCallback(() => {
    window.speechSynthesis.pause()
    setPlaying(false)
  }, [])

  const resume = useCallback(() => {
    window.speechSynthesis.resume()
    setPlaying(true)
  }, [])

  const stop = useCallback(() => {
    window.speechSynthesis.cancel()
    setPlaying(false)
    setProgress(0)
    indexRef.current = 0
  }, [])

  // Cleanup on unmount
  useEffect(() => () => { window.speechSynthesis.cancel() }, [])

  return { playing, progress, play, pause, resume, stop }
}

// ─────────────────────────────────────────────────────────────────────────────

export default function AudioOverviewTab({ notebookId, audioReadySignal }: Props) {
  const { data: overview, refetch } = useGetAudioOverviewQuery(notebookId)
  const [generateAudio, { isLoading: isGenerating }] = useGenerateAudioOverviewMutation()
  const [scriptOpen, setScriptOpen] = useState(false)
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const { playing, progress, play, pause, resume, stop } = useScriptPlayer(overview?.script ?? null)

  // Poll every 4s while audio is in a non-terminal state
  useEffect(() => {
    const isPending = overview != null && !TERMINAL_STATUSES.has(overview.status)
    if (isPending && !pollRef.current) {
      pollRef.current = setInterval(refetch, 4000)
    } else if (!isPending && pollRef.current) {
      clearInterval(pollRef.current)
      pollRef.current = null
    }
    return () => {
      if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null }
    }
  }, [overview, refetch])

  // Refetch when reverb signals audio is ready
  useEffect(() => {
    if (audioReadySignal) refetch()
  }, [audioReadySignal, refetch])

  const handleGenerate = async () => {
    try {
      await generateAudio(notebookId).unwrap()
      toast.info('Generating audio overview… this may take a minute.')
    } catch {
      toast.error('Failed to start audio generation')
    }
  }

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return ''
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m}:${s.toString().padStart(2, '0')}`
  }

  return (
    <div className="flex flex-col h-full p-4 gap-4">
      {/* Header */}
      <div>
        <h3 className="text-sm font-semibold text-white">Audio Overview</h3>
        <p className="text-xs text-slate-400 mt-0.5">
          AI-generated podcast-style summary of your sources
        </p>
      </div>

      {/* Not generated yet or failed */}
      {(!overview || overview.status === 'failed') && (
        <div className="space-y-3">
          {overview?.status === 'failed' && (
            <div className="text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-xl p-3">
              Generation failed: {overview.error ?? 'Unknown error'}
            </div>
          )}
          <button
            onClick={handleGenerate}
            disabled={isGenerating}
            className="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 disabled:opacity-50 text-white rounded-xl py-3 text-sm font-medium transition-all flex items-center justify-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
            {isGenerating ? 'Starting…' : 'Generate Audio Overview'}
          </button>
        </div>
      )}

      {/* Generating in progress */}
      {(overview?.status === 'pending' || overview?.status === 'generating') && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="flex flex-col items-center gap-3 py-6"
        >
          <div className="w-12 h-12 rounded-full bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
            <div className="w-5 h-5 border-2 border-violet-500 border-t-transparent rounded-full animate-spin" />
          </div>
          <div className="text-center">
            <p className="text-sm text-slate-300 font-medium">Generating your podcast…</p>
            <p className="text-xs text-slate-500 mt-1">This usually takes 15–30 seconds</p>
          </div>
        </motion.div>
      )}

      {/* Ready — show player */}
      {overview?.status === 'ready' && (
        <AnimatePresence>
          <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            className="space-y-3"
          >
            {/* Player card */}
            <div className="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 space-y-3">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shrink-0">
                  <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" />
                  </svg>
                </div>
                <div>
                  <p className="text-xs font-medium text-white">Audio Overview</p>
                  {overview.duration_seconds && (
                    <p className="text-[10px] text-slate-500">{formatDuration(overview.duration_seconds)} estimated</p>
                  )}
                </div>
              </div>

              {/* Progress bar */}
              <div className="w-full bg-white/10 rounded-full h-1.5">
                <div
                  className="bg-violet-500 h-1.5 rounded-full transition-all duration-300"
                  style={{ width: `${progress}%` }}
                />
              </div>

              {/* Controls */}
              <div className="flex items-center gap-2">
                {!playing ? (
                  <button
                    onClick={progress > 0 ? resume : play}
                    className="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                  >
                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" />
                    </svg>
                    {progress > 0 ? 'Resume' : 'Listen'}
                  </button>
                ) : (
                  <button
                    onClick={pause}
                    className="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                  >
                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                    </svg>
                    Pause
                  </button>
                )}
                {(playing || progress > 0) && (
                  <button
                    onClick={stop}
                    className="text-slate-400 hover:text-white px-2 py-1.5 rounded-lg text-xs transition-all"
                  >
                    Stop
                  </button>
                )}
              </div>
            </div>

            {/* Regenerate + Script buttons */}
            <div className="flex gap-2">
              <button
                onClick={handleGenerate}
                disabled={isGenerating}
                className="flex-1 border border-white/10 hover:border-violet-500/40 text-slate-400 hover:text-violet-300 rounded-lg py-1.5 text-xs transition-all"
              >
                Regenerate
              </button>
              {overview.script && (
                <button
                  onClick={() => setScriptOpen(!scriptOpen)}
                  className="flex-1 border border-white/10 hover:border-white/20 text-slate-400 hover:text-white rounded-lg py-1.5 text-xs transition-all"
                >
                  {scriptOpen ? 'Hide' : 'View'} Script
                </button>
              )}
            </div>

            <AnimatePresence>
              {scriptOpen && overview.script && (
                <motion.div
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  className="overflow-hidden"
                >
                  <div className="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-3 max-h-60 overflow-y-auto">
                    <pre className="text-[10px] text-slate-300 whitespace-pre-wrap leading-relaxed font-sans">
                      {overview.script}
                    </pre>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </motion.div>
        </AnimatePresence>
      )}
    </div>
  )
}
