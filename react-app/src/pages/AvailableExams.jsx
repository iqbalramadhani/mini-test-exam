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
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold text-slate-800">Ujian</h1>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
            {error}
          </div>
        )}

        {exams.length === 0 ? (
          <div className="text-center py-16 bg-white rounded-xl border border-slate-200">
            <p className="text-slate-400 text-lg">Belum ada ujian yang tersedia</p>
            <p className="text-slate-400 text-sm mt-1">Ujian akan muncul di sini setelah dibuat dan dipublikasi</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {exams.map((exam) => (
              <div
                key={exam.id}
                className="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <h3 className="font-semibold text-slate-800 text-lg truncate">
                      {exam.title}
                    </h3>
                    {exam.description && (
                      <p className="text-slate-500 text-sm mt-1 line-clamp-2">
                        {exam.description}
                      </p>
                    )}
                    <div className="flex items-center gap-4 mt-2 text-xs text-slate-400">
                      <span>{exam.question_count} soal</span>
                      <span>•</span>
                      <span>{exam.time_limit_minutes} menit</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {exam.question_count > 0 ? (
                      <Link
                        to={`/take/${exam.id}`}
                        className="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md transition font-medium"
                      >
                        Mulai Ujian
                      </Link>
                    ) : (
                      <span className="text-xs text-slate-400 border border-slate-200 px-3 py-1.5 rounded-md">
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
