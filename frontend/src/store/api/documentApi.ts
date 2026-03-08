import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
import type { RootState } from '../index'

export type Document = {
  id: number
  title: string
  status: 'uploaded' | 'processing' | 'ready' | 'failed'
  mime_type: string
  created_at: string
}

export const documentApi = createApi({
  reducerPath: 'documentApi',
  baseQuery: fetchBaseQuery({
    baseUrl: '/api',
    prepareHeaders(headers, { getState }) {
      const token = (getState() as RootState).auth.token
      if (token) headers.set('Authorization', `Bearer ${token}`)
      return headers
    },
  }),
  tagTypes: ['Document'],
  endpoints: (build) => ({
    getDocuments: build.query<Document[], number>({
      query: (notebookId) => `/documents?notebook_id=${notebookId}`,
      providesTags: (_result, _err, notebookId) => [{ type: 'Document', id: notebookId }],
    }),
    uploadDocument: build.mutation<Document, { notebookId: number; file: File }>({
      queryFn: async ({ notebookId, file }, { getState }) => {
        const token = (getState() as RootState).auth.token
        const form = new FormData()
        form.append('file', file)
        form.append('notebook_id', String(notebookId))
        const res = await fetch('/api/documents', {
          method: 'POST',
          headers: token ? { Authorization: `Bearer ${token}` } : {},
          body: form,
        })
        if (!res.ok) return { error: { status: res.status, data: await res.json() } }
        return { data: await res.json() }
      },
      invalidatesTags: (_result, _err, { notebookId }) => [{ type: 'Document', id: notebookId }],
    }),
  }),
})

export const { useGetDocumentsQuery, useUploadDocumentMutation } = documentApi
