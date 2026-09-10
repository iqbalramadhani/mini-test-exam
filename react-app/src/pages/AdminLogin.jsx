import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { Navigate } from 'react-router-dom'

export default function AdminLogin() {
  const { user, login } = useAuth()
  const [id, setId] = useState('')
  const [pw, setPw] = useState('')
  const [showPw, setShowPw] = useState(false)
  const [err, setErr] = useState('')

  if (user?.role === 'admin') return <Navigate to="/exams" replace />

  const submit = async (e) => {
    e.preventDefault()
    try {
      await login(id, pw)
      setErr('')
    } catch {
      setErr('Login gagal')
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-cyan-50 px-4">
      <div className="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/40 p-8">
        <div className="text-center mb-6">
          <h1 className="text-3xl font-extrabold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Admin</h1>
          <p className="text-slate-500 text-sm mt-1">Masuk ke panel admin</p>
        </div>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label htmlFor="ad-id" className="block text-xs font-semibold text-slate-600 mb-1">Username / Email</label>
            <input id="ad-id" className="w-full bg-white/70 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition" placeholder="admin" value={id} onChange={e => setId(e.target.value)} />
          </div>
          <div>
            <label htmlFor="ad-pw" className="block text-xs font-semibold text-slate-600 mb-1">Password</label>
            <div className="relative">
              <input id="ad-pw" type={showPw ? "text" : "password"} className="w-full bg-white/70 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition pr-20" placeholder="••••••••" value={pw} onChange={e => setPw(e.target.value)} />
              <button type="button" onClick={() => setShowPw(!showPw)} className="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-indigo-600 hover:text-indigo-800 rounded-lg hover:bg-white/60 transition" aria-label="Toggle password">
                {showPw ? (
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M17.94 17.94A10.06 10.06 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 9.9a3 3 0 0 1 4.24 4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                ) : (
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                )}
              </button>
            </div>
          </div>
          {err && <p className="text-red-500 text-xs">{err}</p>}
          <button className="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl py-2.5 font-bold shadow-lg hover:shadow-xl transition-all">Masuk</button>
        </form>
      </div>
    </div>
  )
}
