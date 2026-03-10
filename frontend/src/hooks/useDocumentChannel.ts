import { useCallback, useEffect } from 'react'
import { getEcho } from '../lib/echo'

export type DocumentUpdate = {
  id: number
  status: 'uploaded' | 'processing' | 'ready' | 'failed'
  title: string
}

export type DocumentFailedUpdate = DocumentUpdate & {
  extraction_error: string | null
}

export type AudioOverviewUpdate = {
  notebook_id: number
  status: string
  duration_seconds: number | null
}

export function useDocumentChannel(
  notebookId: number | null,
  token: string | null,
  onStatusUpdate: (update: DocumentUpdate) => void,
  onFailed: (update: DocumentFailedUpdate) => void,
  onAudioReady?: (update: AudioOverviewUpdate) => void,
) {
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const stableOnStatusUpdate = useCallback(onStatusUpdate, [])
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const stableOnFailed = useCallback(onFailed, [])
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const stableOnAudioReady = useCallback(onAudioReady ?? (() => {}), [])

  useEffect(() => {
    if (!notebookId || !token) return

    const echo = getEcho(token)
    const channel = echo.private(`notebooks.${notebookId}`)

    channel
      .listen('.document.status.updated', stableOnStatusUpdate)
      .listen('.document.processing.failed', stableOnFailed)
      .listen('.audio.overview.ready', stableOnAudioReady)

    return () => {
      channel
        .stopListening('.document.status.updated')
        .stopListening('.document.processing.failed')
        .stopListening('.audio.overview.ready')
    }
  }, [notebookId, token, stableOnStatusUpdate, stableOnFailed, stableOnAudioReady])
}
