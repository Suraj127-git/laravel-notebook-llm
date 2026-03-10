import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
import type { RootState } from '../index'

export type AudioOverview = {
  id: number
  notebook_id: number
  status: 'pending' | 'generating' | 'ready' | 'failed'
  duration_seconds: number | null
  script: string | null
  error: string | null
  created_at: string
  updated_at: string
}

export const audioApi = createApi({
  reducerPath: 'audioApi',
  baseQuery: fetchBaseQuery({
    baseUrl: '/api',
    prepareHeaders(headers, { getState }) {
      const token = (getState() as RootState).auth.token
      if (token) headers.set('Authorization', `Bearer ${token}`)
      return headers
    },
  }),
  tagTypes: ['AudioOverview'],
  endpoints: (build) => ({
    getAudioOverview: build.query<AudioOverview | null, number>({
      query: (notebookId) => `/notebooks/${notebookId}/audio-overview`,
      providesTags: (_result, _err, notebookId) => [{ type: 'AudioOverview', id: notebookId }],
      transformErrorResponse: () => null,
    }),
    generateAudioOverview: build.mutation<AudioOverview, number>({
      query: (notebookId) => ({ url: `/notebooks/${notebookId}/audio-overview`, method: 'POST' }),
      invalidatesTags: (_result, _err, notebookId) => [{ type: 'AudioOverview', id: notebookId }],
    }),
  }),
})

export const {
  useGetAudioOverviewQuery,
  useGenerateAudioOverviewMutation,
} = audioApi
