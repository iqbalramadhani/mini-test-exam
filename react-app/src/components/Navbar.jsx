import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useState, useEffect } from 'react'

export default function Navbar() {
  const { user, logout } = useAuth()
  const [guestName, setGuestName] = useState('')
  const navigate = useNavigate()

  useEffect(() => {
    const updateGuestName = () => {
      const saved = localStorage.getItem('guest_name')
      if (saved) setGuestName(saved)
      else setGuestName('')
    }
    updateGuestName()

    window.addEventListener('guest_name_updated', updateGuestName)
    window.addEventListener('storage', updateGuestName)
    
    return () => {
      window.removeEventListener('guest_name_updated', updateGuestName)
      window.removeEventListener('storage', updateGuestName)
    }
  }, [])

  const handleReset = () => {
    if (user) {
      logout()
    } else {
      localStorage.removeItem('guest_name')
      setGuestName('')
      window.dispatchEvent(new Event('guest_name_updated'))
      navigate('/')
    }
  }

  const displayName = user ? (user.name || user.username) : (guestName || 'Tamu')

  return (
    <nav className="sticky top-0 z-50 bg-white/70 backdrop-blur-md border-b border-slate-200/50 shadow-sm">
      <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <Link to="/" className="text-xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent hover:opacity-80 transition">
          Ujian
        </Link>

        <div className="flex items-center gap-4">
          <span className="text-sm text-slate-600">
            Halo, <strong className="text-slate-800">{displayName}</strong>
          </span>

          {user && (
            <Link to="/dashboard" className="text-sm text-indigo-600 font-medium hover:text-indigo-800 transition">Dashboard</Link>
          )}
          {user?.role === 'admin' && (
            <Link to="/admin" className="text-sm text-indigo-600 font-medium hover:text-indigo-800 transition">Admin</Link>
          )}
          <button onClick={handleReset} className="text-sm bg-slate-100/80 hover:bg-red-50 hover:text-red-600 text-slate-700 px-4 py-1.5 rounded-full transition-all duration-300">
            {user ? 'Keluar' : 'Reset'}
          </button>
        </div>
      </div>
    </nav>
  )
}
