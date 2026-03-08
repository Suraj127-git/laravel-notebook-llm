import { createSlice, type PayloadAction } from '@reduxjs/toolkit'

type UIState = {
  sidebarOpen: boolean
  selectedNotebookId: number | null
  theme: 'dark' | 'light'
}

const initialState: UIState = {
  sidebarOpen: true,
  selectedNotebookId: null,
  theme: 'dark',
}

const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    setSidebarOpen(state, action: PayloadAction<boolean>) {
      state.sidebarOpen = action.payload
    },
    setSelectedNotebookId(state, action: PayloadAction<number | null>) {
      state.selectedNotebookId = action.payload
    },
    setTheme(state, action: PayloadAction<'dark' | 'light'>) {
      state.theme = action.payload
    },
  },
})

export const { setSidebarOpen, setSelectedNotebookId, setTheme } = uiSlice.actions
export default uiSlice.reducer
