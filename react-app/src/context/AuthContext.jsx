import { createContext, useContext, useState, useEffect } from 'react'
import { Navigate } from 'react-router-dom'
import { authApi } from '../api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    authApi.me()
      .then((data) => setUser(data.user))
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

  const login = async (identifier, password) => {
    const data = await authApi.login({ identifier, password })
    setUser(data.user)
    return data
  }

  const register = async (username, email, password) => {
    const data = await authApi.register({ username, email, password })
    setUser(data.user)
    return data
  }

  const logout = async () => {
    await authApi.logout()
    setUser(null)
  }

  const updateProfile = async (name) => {
    const data = await authApi.updateProfile({ name })
    setUser(data.user)
    return data
  }

  const changePassword = async (currentPassword, newPassword) => {
    await authApi.changePassword({ current_password: currentPassword, new_password: newPassword })
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, isAuthenticated: !!user, updateProfile, changePassword }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}

export function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  return children
}
