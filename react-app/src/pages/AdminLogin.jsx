import { useState, useEffect } from 'react'
import { useAuth } from '../context/AuthContext'
import { Navigate } from 'react-router-dom'
import { authApi } from '../api'

export default function AdminLogin() {
  const { user } = useAuth()
  const [err, setErr] = useState('')
  const [googleBusy, setGoogleBusy] = useState(false)

  // Restore regular form if user navigates back from Google flow
  useEffect(() => {
    const handler = (e) => {
      if (e.origin !== window.location.origin) return
      if (e.data?.type === 'google_admin_login_success') {
        // Close popup; AuthProvider will re-fetch /api/auth/me on next mount
        if (window.closePopup) window.closePopup()
      }
      if (e.data?.type === 'google_admin_login_error') {
        setErr(decodeURIComponent(e.data.message))
        if (window.closePopup) window.closePopup()
      }
    }
    window.addEventListener('message', handler)
    return () => window.removeEventListener('message', handler)
  }, [])

  if (user?.role === 'admin') return <Navigate to="/admin" replace />



  const openGoogleLogin = () => {
    setGoogleBusy(true)
    const width = 500, height = 600
    const left = (window.screen.width / 2) - (width / 2)
    const top = (window.screen.height / 2) - (height / 2)
    const popup = window.open(
      authApi.adminGoogleLogin(),
      'googleAdminLogin',
      `width=${width},height=${height},left=${left},top=${top}`
    )
    // Fallback: close popup after 90s if message event never fires
    const timer = setTimeout(() => {
      if (popup && !popup.closed) {
        popup.close()
        setGoogleBusy(false)
      }
    }, 90000)
    window.closePopup = () => {
      clearTimeout(timer)
      setGoogleBusy(false)
      delete window.closePopup
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-cyan-50 px-4">
      <div className="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/40 p-8">
        <div className="text-center mb-6">
          <h1 className="text-3xl font-extrabold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Admin</h1>
          <p className="text-slate-500 text-sm mt-1">Masuk ke panel admin</p>
        </div>
        {err && <p className="text-red-500 text-sm text-center mb-4">{err}</p>}
        <button
          type="button"
          onClick={openGoogleLogin}
          disabled={googleBusy}
          className="w-full flex items-center justify-center gap-2 bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl py-2.5 font-semibold transition-all disabled:opacity-50"
        >
          <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.9 33.6 29.4 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6 29.2 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.2-2.7-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.3 15.5 18.8 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6 29.2 4 24 4 16.1 4 9.6 8.6 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c4.9 0 9.4-1.8 12.8-4.8l-6.2-5.4C29.2 35.2 26.7 36 24 36c-5.3 0-9.8-3.4-11.4-8.2l-6.5 5C9.5 39.3 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.4C38.9 39.5 44 34 44 24c0-1.3-.2-2.7-.4-3.9z"/></svg>
          {googleBusy ? 'Memproses...' : 'Login dengan Google'}
        </button>
      </div>
    </div>
  )
}
