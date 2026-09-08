import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Navbar() {
  const { user, logout } = useAuth()

  return (
    <nav className="bg-white border-b border-slate-200 shadow-sm">
      <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <Link to="/" className="text-xl font-bold text-slate-800">
          UjianWeb
        </Link>

        {user ? (
          <div className="flex items-center gap-4">
            <span className="text-sm text-slate-600">
              Halo, <strong className="text-slate-800">{user.username}</strong>
            </span>
            <Link
              to="/dashboard"
              className="text-sm text-slate-600 hover:text-slate-900 transition"
            >
              Dashboard
            </Link>
            <button
              onClick={logout}
              className="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-md transition"
            >
              Keluar
            </button>
          </div>
        ) : (
          <div className="flex items-center gap-2">
            <Link
              to="/login"
              className="text-sm text-slate-600 hover:text-slate-900 transition"
            >
              Masuk
            </Link>
            <Link
              to="/register"
              className="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md transition"
            >
              Daftar
            </Link>
          </div>
        )}
      </div>
    </nav>
  )
}
