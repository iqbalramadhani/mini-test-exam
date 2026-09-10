import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { examApi } from '../api'

export default function CreateExam() {
  const [title, setTitle] = useState('')
  const [desc, setDesc] = useState('')
  const [time, setTime] = useState(60)
  const [loading, setLoading] = useState(false)
  const navigate = useNavigate()

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    try {
      const res = await examApi.create({ title, description: desc, time_limit_minutes: time })
      navigate(`/exam/${res.exam.id}/build`)
    } catch {
      alert('Gagal membuat ujian')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-xl mx-auto p-8">
      <h1 className="text-2xl font-bold mb-4">Buat Ujian Baru</h1>
      <form onSubmit={submit} className="space-y-4">
        <input className="w-full border rounded-xl px-4 py-2.5" placeholder="Judul" value={title} onChange={e => setTitle(e.target.value)} required />
        <textarea className="w-full border rounded-xl px-4 py-2.5" placeholder="Deskripsi" value={desc} onChange={e => setDesc(e.target.value)} />
        <input type="number" className="w-full border rounded-xl px-4 py-2.5" placeholder="Durasi (menit)" value={time} onChange={e => setTime(parseInt(e.target.value) || 60)} min={1} />
        <button disabled={loading} className="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl py-2.5 font-bold">Buat</button>
      </form>
    </div>
  )
}
