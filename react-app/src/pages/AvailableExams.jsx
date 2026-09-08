import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { attemptApi, examApi } from '../api'
import { useAuth } from '../context/AuthContext'

export default function AvailableExams() {
  const { user } = useAuth()
  const [exams, setExams] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [activeTab, setActiveTab] = useState('available')

  useEffect(() => {
    Promise.all([
      attemptApi.listPublished(),
      user ? examApi.list() : Promise.resolve({ exams: [] }),
    ])
      .then(([pubData, dashData]) => {
        setExams(pubData.exams || [])
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="min-h-screen bg-transparent flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-transparent">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex items-center justify-between mb-8">
          <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight">Ujian Tersedia</h1>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
            {error}
          </div>
        )}

        {exams.length === 0 ? (
          <div className="text-center py-20 bg-white/50 backdrop-blur-md rounded-3xl border border-white/60 shadow-sm">
            <div className="w-16 h-16 mx-auto mb-4 bg-indigo-50 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <p className="text-slate-600 text-lg font-medium">Belum ada ujian yang tersedia</p>
            <p className="text-slate-400 text-sm mt-1">Ujian akan muncul di sini setelah dibuat dan dipublikasi</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {exams.map((exam) => (
              <div
                key={exam.id}
                className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-5 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
              >
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <h3 className="font-bold text-slate-800 text-xl truncate group-hover:text-indigo-700 transition-colors">
                      {exam.title}
                    </h3>
                    {exam.description && (
                      <p className="text-slate-500 text-sm mt-1.5 line-clamp-2 leading-relaxed">
                        {exam.description}
                      </p>
                    )}
                    <div className="flex items-center flex-wrap gap-2 mt-3 text-sm">
                      <span className="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md font-medium text-xs">
                        {exam.question_count} soal
                      </span>
                      <span className="text-slate-300">•</span>
                      <span className="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md font-medium text-xs">
                        {exam.time_limit_minutes} menit
                      </span>
                      <span className="text-slate-300">•</span>
                      <span className="text-slate-500 text-xs">{exam.attempt_count ?? 0} peserta</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {exam.question_count > 0 ? (
                      <Link
                        to={`/take/${exam.id}`}
                        className="text-sm bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all font-medium inline-block"
                      >
                        Mulai Ujian
                      </Link>
                    ) : (
                      <span className="text-xs text-slate-500 bg-slate-100 border border-slate-200 px-4 py-2.5 rounded-xl font-medium inline-block">
                        Belum ada soal
                      </span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
