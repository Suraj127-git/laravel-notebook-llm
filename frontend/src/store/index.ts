import { configureStore } from '@reduxjs/toolkit'
import { audioApi } from './api/audioApi'
import { documentApi } from './api/documentApi'
import { noteApi } from './api/noteApi'
import { notebookApi } from './api/notebookApi'
import authReducer from './slices/authSlice'
import uiReducer from './slices/uiSlice'

export const store = configureStore({
  reducer: {
    auth: authReducer,
    ui: uiReducer,
    [notebookApi.reducerPath]: notebookApi.reducer,
    [documentApi.reducerPath]: documentApi.reducer,
    [noteApi.reducerPath]: noteApi.reducer,
    [audioApi.reducerPath]: audioApi.reducer,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware().concat(
      notebookApi.middleware,
      documentApi.middleware,
      noteApi.middleware,
      audioApi.middleware,
    ),
})

export type RootState = ReturnType<typeof store.getState>
export type AppDispatch = typeof store.dispatch
