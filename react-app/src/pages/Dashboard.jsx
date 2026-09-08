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
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold text-slate-800">Daftar Ujian</h1>
          <button
            onClick={() => setShowCreate(!showCreate)}
            className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
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
          <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-slate-800 mb-4">Ujian Baru</h2>
            <form onSubmit={handleCreate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Judul Ujian</label>
                <input
                  type="text"
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Contoh: Ujian Tengah Semester Matematika"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                <textarea
                  value={newDesc}
                  onChange={(e) => setNewDesc(e.target.value)}
                  className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
              <div className="flex gap-2 justify-end">
                <button
                  type="button"
                  onClick={() => setShowCreate(false)}
                  className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                  Simpan
                </button>
              </div>
            </form>
          </div>
        )}

        {editingExam && (
          <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 className="text-lg font-semibold text-slate-800 mb-4">Edit Ujian</h2>
            <form onSubmit={handleUpdate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Judul Ujian</label>
                <input
                  type="text"
                  value={editTitle}
                  onChange={(e) => setEditTitle(e.target.value)}
                  className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                <textarea
                  value={editDesc}
                  onChange={(e) => setEditDesc(e.target.value)}
                  className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
              <div className="flex gap-2 justify-end">
                <button
                  type="button"
                  onClick={() => setEditingExam(null)}
                  className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                  Simpan Perubahan
                </button>
              </div>
            </form>
          </div>
        )}

        {exams.length === 0 ? (
          <div className="text-center py-16 bg-white rounded-xl border border-slate-200">
            <p className="text-slate-400 text-lg">Belum ada ujian</p>
            <p className="text-slate-400 text-sm mt-1">Klik tombol di atas untuk membuat ujian baru</p>
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
                      <span>{exam.creator}</span>
                      <span>•</span>
                      <span>{exam.time_limit_minutes} menit</span>
                      <span>•</span>
                      <span
                        onClick={() => handleToggleStatus(exam)}
                        className="cursor-pointer hover:opacity-70 transition"
                      >
                        {exam.is_published ? (
                          <span className="text-green-500">Publik</span>
                        ) : (
                          <span className="text-amber-500">Draft</span>
                        )}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <button
                      onClick={() => openEdit(exam)}
                      className="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-md transition"
                    >
                      Edit
                    </button>
                    <Link
                      to={`/exam/${exam.id}/build`}
                      className="text-sm bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-md transition"
                    >
                      Edit Soal
                    </Link>
                    <button
                      onClick={() => handleDelete(exam.id)}
                      className="text-sm text-red-500 hover:text-red-700 px-2 py-1.5 transition"
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
