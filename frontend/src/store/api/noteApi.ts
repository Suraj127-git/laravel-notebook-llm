import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
import type { RootState } from '../index'

export type Note = {
  id: number
  notebook_id: number
  chat_message_id: number | null
  title: string | null
  content: string
  pinned: boolean
  created_at: string
  updated_at: string
}

export const noteApi = createApi({
  reducerPath: 'noteApi',
  baseQuery: fetchBaseQuery({
    baseUrl: '/api',
    prepareHeaders(headers, { getState }) {
      const token = (getState() as RootState).auth.token
      if (token) headers.set('Authorization', `Bearer ${token}`)
      return headers
    },
  }),
  tagTypes: ['Note'],
  endpoints: (build) => ({
    getNotes: build.query<Note[], number>({
      query: (notebookId) => `/notebooks/${notebookId}/notes`,
      providesTags: (_result, _err, notebookId) => [{ type: 'Note', id: notebookId }],
    }),
    createNote: build.mutation<Note, { notebook_id: number; content: string; title?: string; chat_message_id?: number }>({
      query: (body) => ({ url: '/notes', method: 'POST', body }),
      invalidatesTags: (_result, _err, { notebook_id }) => [{ type: 'Note', id: notebook_id }],
    }),
    updateNote: build.mutation<Note, { id: number; notebook_id: number } & Partial<Pick<Note, 'title' | 'content' | 'pinned'>>>({
      query: ({ id, ...body }) => ({ url: `/notes/${id}`, method: 'PATCH', body }),
      invalidatesTags: (_result, _err, { notebook_id }) => [{ type: 'Note', id: notebook_id }],
    }),
    deleteNote: build.mutation<void, { id: number; notebook_id: number }>({
      query: ({ id }) => ({ url: `/notes/${id}`, method: 'DELETE' }),
      invalidatesTags: (_result, _err, { notebook_id }) => [{ type: 'Note', id: notebook_id }],
    }),
  }),
})

export const {
  useGetNotesQuery,
  useCreateNoteMutation,
  useUpdateNoteMutation,
  useDeleteNoteMutation,
} = noteApi
