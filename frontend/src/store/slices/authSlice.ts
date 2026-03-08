import { createSlice, type PayloadAction } from '@reduxjs/toolkit'

export type AuthUser = {
  id: number
  name: string
  email: string
}

type AuthState = {
  user: AuthUser | null
  token: string | null
  loading: boolean
}

const initialState: AuthState = {
  user: null,
  token: localStorage.getItem('auth_token'),
  loading: false,
}

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser(state, action: PayloadAction<{ user: AuthUser; token: string }>) {
      state.user = action.payload.user
      state.token = action.payload.token
      state.loading = false
    },
    clearUser(state) {
      state.user = null
      state.token = null
    },
    setLoading(state, action: PayloadAction<boolean>) {
      state.loading = action.payload
    },
  },
})

export const { setUser, clearUser, setLoading } = authSlice.actions
export default authSlice.reducer
