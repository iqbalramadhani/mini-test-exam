import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { examApi } from '../api'

export default function Dashboard() {
  const [exams, setExams] = useState([])
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)
  const [newTitle, setNewTitle] = useState('')
  const [newDesc, setNewDesc] = useState('')
  const [newTime, setNewTime] = useState(60)
  const [editingExam, setEditingExam] = useState(null)
  const [editTitle, setEditTitle] = useState('')
  const [editDesc, setEditDesc] = useState('')
  const [editTime, setEditTime] = useState(60)
  const [error, setError] = useState('')

  useEffect(() => {
    examApi.list()
      .then((data) => setExams(data.exams))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [])

  const handleCreate = async (e) => {
    e.preventDefault()
    if (!newTitle.trim()) return
    try {
      const data = await examApi.create({ title: newTitle, description: newDesc, time_limit_minutes: newTime })
      setExams([data.exam, ...exams])
      setNewTitle('')
      setNewDesc('')
      setNewTime(60)
      setShowCreate(false)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleToggleStatus = async (exam) => {
    const newValue = exam.is_published ? 0 : 1
    try {
      await examApi.update(exam.id, {
        title: exam.title,
        description: exam.description || '',
        time_limit_minutes: exam.time_limit_minutes,
        is_published: newValue,
      })
      setExams(exams.map((ex) => ex.id === exam.id ? { ...ex, is_published: newValue } : ex))
    } catch (err) {
      setError(err.message)
    }
  }

  const openEdit = (exam) => {
    setEditingExam(exam)
    setEditTitle(exam.title)
    setEditDesc(exam.description || '')
    setEditTime(exam.time_limit_minutes)
  }

  const handleUpdate = async (e) => {
    e.preventDefault()
    if (!editTitle.trim()) return
    try {
      await examApi.update(editingExam.id, { title: editTitle, description: editDesc, time_limit_minutes: editTime })
      setExams(exams.map((ex) => ex.id === editingExam.id ? { ...ex, title: editTitle, description: editDesc, time_limit_minutes: editTime } : ex))
      setEditingExam(null)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleDelete = async (id) => {
    if (!confirm('Hapus ujian ini?')) return
    try {
      await examApi.del(id)
      setExams(exams.filter((e) => e.id !== id))
    } catch (err) {
      setError(err.message)
    }
  }

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
          <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight">Daftar Ujian</h1>
          <button
            onClick={() => setShowCreate(!showCreate)}
            className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-full shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300"
          >
            + Buat Ujian Baru
          </button>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
            {error}
          </div>
        )}

        {showCreate && (
          <div className="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/50 p-6 mb-8 transition-all">
            <h2 className="text-xl font-bold text-slate-800 mb-5">Ujian Baru</h2>
            <form onSubmit={handleCreate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Judul Ujian</label>
                <input
                  type="text"
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  placeholder="Contoh: Ujian Tengah Semester Matematika"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                <textarea
                  value={newDesc}
                  onChange={(e) => setNewDesc(e.target.value)}
                  className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  rows={2}
                  placeholder="Opsional"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  Waktu (menit): <span className="text-blue-600 font-semibold">{newTime}</span>
                </label>
                <input
                  type="range"
                  min="15"
                  max="180"
                  step="15"
                  value={newTime}
                  onChange={(e) => setNewTime(Number(e.target.value))}
                  className="w-full accent-blue-600"
                />
              </div>
              <div className="flex gap-3 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setShowCreate(false)}
                  className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2 font-medium"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition-all"
                >
                  Simpan
                </button>
              </div>
            </form>
          </div>
        )}

        {editingExam && (
          <div className="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/50 p-6 mb-8 transition-all">
            <h2 className="text-xl font-bold text-slate-800 mb-5">Edit Ujian</h2>
            <form onSubmit={handleUpdate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Judul Ujian</label>
                <input
                  type="text"
                  value={editTitle}
                  onChange={(e) => setEditTitle(e.target.value)}
                  className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                <textarea
                  value={editDesc}
                  onChange={(e) => setEditDesc(e.target.value)}
                  className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                  rows={2}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                  Waktu (menit): <span className="text-blue-600 font-semibold">{editTime}</span>
                </label>
                <input
                  type="range"
                  min="15"
                  max="180"
                  step="15"
                  value={editTime}
                  onChange={(e) => setEditTime(Number(e.target.value))}
                  className="w-full accent-blue-600"
                />
              </div>
              <div className="flex gap-3 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setEditingExam(null)}
                  className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2 font-medium"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition-all"
                >
                  Simpan Perubahan
                </button>
              </div>
            </form>
          </div>
        )}

        {exams.length === 0 ? (
          <div className="text-center py-20 bg-white/50 backdrop-blur-md rounded-3xl border border-white/60 shadow-sm">
            <div className="w-16 h-16 mx-auto mb-4 bg-indigo-50 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <p className="text-slate-600 text-lg font-medium">Belum ada ujian</p>
            <p className="text-slate-400 text-sm mt-1">Klik tombol di atas untuk membuat ujian baru</p>
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
                        {exam.time_limit_minutes} menit
                      </span>
                      <span className="text-slate-300">•</span>
                      <span className="text-slate-500 text-xs">Oleh {exam.creator}</span>
                      <span className="text-slate-300">•</span>
                      <button
                        onClick={() => handleToggleStatus(exam)}
                        className="hover:opacity-80 transition"
                      >
                        {exam.is_published ? (
                          <span className="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-md font-medium text-xs border border-emerald-200">
                            Publik
                          </span>
                        ) : (
                          <span className="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-md font-medium text-xs border border-amber-200">
                            Draft
                          </span>
                        )}
                      </button>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <button
                      onClick={() => openEdit(exam)}
                      className="text-sm bg-slate-100/80 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl font-medium transition-all"
                    >
                      Edit
                    </button>
                    <Link
                      to={`/exam/${exam.id}/build`}
                      className="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-4 py-2 rounded-xl font-medium transition-all"
                    >
                      Edit Soal
                    </Link>
                    <button
                      onClick={() => handleDelete(exam.id)}
                      className="text-sm text-red-500 hover:bg-red-50 px-3 py-2 rounded-xl font-medium transition-all"
                    >
                      Hapus
                    </button>
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
