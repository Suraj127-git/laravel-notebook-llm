import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
import type { RootState } from '../index'

export type Notebook = {
  id: number
  name: string
  description: string | null
  emoji: string
  documents_count?: number
  created_at: string
  updated_at: string
}

export type Message = {
  id: number
  role: 'user' | 'assistant'
  content: string
  metadata?: { sources?: { title: string; document_id: number }[] }
  created_at: string
}

export const notebookApi = createApi({
  reducerPath: 'notebookApi',
  baseQuery: fetchBaseQuery({
    baseUrl: '/api',
    prepareHeaders(headers, { getState }) {
      const token = (getState() as RootState).auth.token
      if (token) headers.set('Authorization', `Bearer ${token}`)
      return headers
    },
  }),
  tagTypes: ['Notebook', 'Message'],
  endpoints: (build) => ({
    getNotebooks: build.query<Notebook[], void>({
      query: () => '/notebooks',
      providesTags: ['Notebook'],
    }),
    createNotebook: build.mutation<Notebook, { name: string; emoji?: string }>({
      query: (body) => ({ url: '/notebooks', method: 'POST', body }),
      invalidatesTags: ['Notebook'],
    }),
    updateNotebook: build.mutation<Notebook, { id: number } & Partial<Pick<Notebook, 'name' | 'description' | 'emoji'>>>({
      query: ({ id, ...body }) => ({ url: `/notebooks/${id}`, method: 'PATCH', body }),
      invalidatesTags: ['Notebook'],
    }),
    deleteNotebook: build.mutation<void, number>({
      query: (id) => ({ url: `/notebooks/${id}`, method: 'DELETE' }),
      invalidatesTags: ['Notebook'],
    }),
    getMessages: build.query<Message[], number>({
      query: (notebookId) => `/chat/history/${notebookId}`,
      providesTags: (_result, _err, notebookId) => [{ type: 'Message', id: notebookId }],
    }),
  }),
})

export const {
  useGetNotebooksQuery,
  useCreateNotebookMutation,
  useUpdateNotebookMutation,
  useDeleteNotebookMutation,
  useGetMessagesQuery,
} = notebookApi
