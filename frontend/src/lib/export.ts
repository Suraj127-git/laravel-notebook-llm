export type ExportMessage = {
  role: 'user' | 'assistant'
  content: string
}

export function exportAsMarkdown(messages: ExportMessage[], notebookName: string): void {
  const date = new Date().toISOString().split('T')[0]
  const lines: string[] = [
    `# Chat Export — ${notebookName}`,
    `_Exported: ${date}_`,
    '',
  ]

  for (const msg of messages) {
    if (msg.role === 'user') {
      lines.push(`**You:** ${msg.content}`, '')
    } else {
      lines.push(`**Assistant:** ${msg.content}`, '')
    }
  }

  const blob = new Blob([lines.join('\n')], { type: 'text/markdown' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `chat-${notebookName.toLowerCase().replace(/\s+/g, '-')}-${date}.md`
  a.click()
  URL.revokeObjectURL(url)
}
