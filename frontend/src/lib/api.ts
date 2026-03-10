import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
})

export default api

export async function getCsrfCookie() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

