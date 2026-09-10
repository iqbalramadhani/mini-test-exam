import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { useState, useEffect } from 'react'

export default function Navbar() {
  const { user, logout } = useAuth()
  const [guestName, setGuestName] = useState('')

  useEffect(() => {
    const saved = localStorage.getItem('guest_name')
    if (saved) setGuestName(saved)
  }, [])

  const handleNameChange = (e) => {
    const val = e.target.value
    setGuestName(val)
    localStorage.setItem('guest_name', val)
  }

  const displayName = user ? (user.name || user.username) : (guestName || 'Tamu')

  return (
    <nav className="sticky top-0 z-50 bg-white/70 backdrop-blur-md border-b border-slate-200/50 shadow-sm">
      <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <Link to="/exams" className="text-xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent hover:opacity-80 transition">
          Ujian
        </Link>

        <div className="flex items-center gap-4">
          <span className="text-sm text-slate-600">
            Halo, <strong className="text-slate-800">{displayName}</strong>
          </span>

          {!user && (
            <input
              type="text"
              placeholder="Nama kamu"
              value={guestName}
              onChange={handleNameChange}
              className="text-sm border border-slate-300 rounded-full px-3 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-400 w-32"
            />
          )}

          <Link to="/exams" className="text-sm text-slate-600 hover:text-slate-900 transition">Ujian</Link>
          {user?.role === 'admin' && (
            <Link to="/admin" className="text-sm text-indigo-600 font-medium hover:text-indigo-800 transition">Admin</Link>
          )}
          <button onClick={() => user ? logout() : (localStorage.removeItem('guest_name'), setGuestName(''))} className="text-sm bg-slate-100/80 hover:bg-red-50 hover:text-red-600 text-slate-700 px-4 py-1.5 rounded-full transition-all duration-300">
            {user ? 'Keluar' : 'Reset'}
          </button>
        </div>
      </div>
    </nav>
  )
}
