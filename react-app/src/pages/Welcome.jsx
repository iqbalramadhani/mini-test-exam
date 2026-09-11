import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Welcome() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [name, setName] = useState('')

  useEffect(() => {
    const savedName = localStorage.getItem('guest_name')
    if (savedName) setName(savedName)
  }, [])

  const handleStart = (e) => {
    e.preventDefault()
    if (!user) {
      if (!name.trim()) return
      localStorage.setItem('guest_name', name.trim())
      window.dispatchEvent(new Event('guest_name_updated'))
    }
    navigate('/exams')
  }

  return (
    <div className="min-h-[calc(100vh-4rem)] flex flex-col items-center justify-center bg-transparent px-4 py-12">
      <div className="w-full max-w-2xl text-center relative z-10">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-gradient-to-tr from-indigo-200 to-blue-200 rounded-full blur-3xl opacity-60 pointer-events-none -z-10"></div>
        
        <h1 className="text-5xl md:text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-700 to-indigo-700 tracking-tight mb-6 drop-shadow-sm">
          Selamat Datang
        </h1>
        
        <p className="text-lg md:text-xl text-slate-600 mb-8 max-w-lg mx-auto leading-relaxed">
          Platform ujian interaktif yang dirancang untuk membantu Anda berlatih dan mengukur kemampuan dengan mudah dan efisien.
        </p>

        <form onSubmit={handleStart} className="flex flex-col items-center gap-4 mb-4">
          {!user && (
            <input
              type="text"
              required
              placeholder="Masukkan nama kamu..."
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full max-w-sm text-center border-2 border-slate-200/60 bg-white/60 focus:bg-white rounded-full px-6 py-4 text-lg focus:outline-none focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-400 transition-all shadow-sm"
            />
          )}
          <button
            type="submit"
            disabled={!user && !name.trim()}
            className="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-lg font-bold px-10 py-4 rounded-full shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all"
          >
            Lihat Daftar Ujian
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </button>
        </form>
      </div>

      <div className="mt-24 grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl mx-auto">
        <div className="bg-white/80 backdrop-blur-xl p-6 rounded-3xl border border-white/50 shadow-sm hover:shadow-md transition-shadow text-center">
          <div className="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-4">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
          </div>
          <h3 className="text-lg font-bold text-slate-800 mb-2">Simulasi & Latihan</h3>
          <p className="text-sm text-slate-500">Tersedia mode Tryout untuk simulasi nyata dan mode Latihan dengan pembahasan detail.</p>
        </div>
        <div className="bg-white/80 backdrop-blur-xl p-6 rounded-3xl border border-white/50 shadow-sm hover:shadow-md transition-shadow text-center">
          <div className="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
          </div>
          <h3 className="text-lg font-bold text-slate-800 mb-2">Cepat & Responsif</h3>
          <p className="text-sm text-slate-500">Desain antarmuka modern yang nyaman digunakan di berbagai ukuran layar dan perangkat.</p>
        </div>
        <div className="bg-white/80 backdrop-blur-xl p-6 rounded-3xl border border-white/50 shadow-sm hover:shadow-md transition-shadow text-center">
          <div className="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <h3 className="text-lg font-bold text-slate-800 mb-2">Penilaian Langsung</h3>
          <p className="text-sm text-slate-500">Dapatkan hasil ujian secara instan segera setelah Anda mengumpulkan jawaban ujian.</p>
        </div>
      </div>
    </div>
  )
}
