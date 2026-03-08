export type Source = {
  title: string
  document_id: number
}

export function createChatStream(
  notebookId: number | string,
  message: string,
  onDelta: (delta: string) => void,
  onDone: () => void,
  onError: (err: unknown) => void,
  onSources?: (sources: Source[]) => void,
) {
  const token = localStorage.getItem('auth_token')
  const params = new URLSearchParams({
    notebook_id: String(notebookId),
    message,
    ...(token && { token }),
  })

  const eventSource = new EventSource(`/api/chat/stream?${params.toString()}`, {
    withCredentials: true,
  } as EventSourceInit)

  eventSource.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)

      if (data.delta !== undefined) {
        onDelta(data.delta)
      }

      if (data.sources !== undefined) {
        onSources?.(data.sources as Source[])
      }

      if (data.done) {
        eventSource.close()
        onDone()
      }

      if (data.error) {
        eventSource.close()
        onError(new Error(data.error))
      }
    } catch (e) {
      console.error('SSE parse error', e)
    }
  }

  eventSource.onerror = (err) => {
    eventSource.close()
    onError(err)
  }

  return eventSource
}

