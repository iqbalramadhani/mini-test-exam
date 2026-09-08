import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Navbar() {
  const { user, logout } = useAuth()

  return (
    <nav className="sticky top-0 z-50 bg-white/70 backdrop-blur-md border-b border-slate-200/50 shadow-sm">
      <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <Link to="/" className="text-xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent hover:opacity-80 transition">
          Ujian
        </Link>

        {user ? (
          <div className="flex items-center gap-4">
            <span className="text-sm text-slate-600">
              Halo, <strong className="text-slate-800">{user.name || user.username}</strong>
            </span>
            <Link
              to="/exams"
              className="text-sm text-slate-600 hover:text-slate-900 transition"
            >
              Ujian
            </Link>
            <Link
              to="/dashboard"
              className="text-sm text-slate-600 hover:text-slate-900 transition"
            >
              Dashboard
            </Link>
            <Link
              to="/profile"
              className="text-sm text-slate-600 hover:text-slate-900 transition"
            >
              Profil
            </Link>
            <button
              onClick={logout}
              className="text-sm bg-slate-100/80 hover:bg-red-50 hover:text-red-600 text-slate-700 px-4 py-1.5 rounded-full transition-all duration-300"
            >
              Keluar
            </button>
          </div>
        ) : (
          <div className="flex items-center gap-2">
            <Link
              to="/login"
              className="text-sm font-medium text-slate-600 hover:text-indigo-600 transition"
            >
              Masuk
            </Link>
            <Link
              to="/register"
              className="text-sm font-medium bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-5 py-2 rounded-full transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5"
            >
              Daftar
            </Link>
          </div>
        )}
      </div>
    </nav>
  )
}
