import { useEffect, useState } from 'react'
import { api, getCsrfCookie } from '../lib/api'

export type User = {
  id: number
  name: string
  email: string
}

export function useAuth() {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Check for stored token on mount
    const token = localStorage.getItem('auth_token')
    if (token) {
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }
    
    api
      .get<User>('/user')
      .then((res) => setUser(res.data))
      .catch(() => {
        setUser(null)
        // Clear invalid token
        localStorage.removeItem('auth_token')
        delete api.defaults.headers.common['Authorization']
      })
      .finally(() => setLoading(false))
  }, [])

  const login = async (email: string, password: string) => {
    await getCsrfCookie()
    const { data } = await api.post<{user: User, token: string}>('/login', { email, password })
    // Store the token for future requests
    localStorage.setItem('auth_token', data.token)
    api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    setUser(data.user)
    return data.user
  }

  const register = async (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
  ) => {
    await getCsrfCookie()
    const { data } = await api.post<{user: User, token: string}>('/register', {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    })
    // Store the token for future requests
    localStorage.setItem('auth_token', data.token)
    api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    setUser(data.user)
    return data.user
  }

  const logout = async () => {
    await api.post('/logout')
    setUser(null)
    // Clear token
    localStorage.removeItem('auth_token')
    delete api.defaults.headers.common['Authorization']
  }

  return { user, loading, login, register, logout }
}

