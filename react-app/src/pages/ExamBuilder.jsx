import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { examApi } from '../api'

export default function ExamBuilder() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [exam, setExam] = useState(null)
  const [questions, setQuestions] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

  useEffect(() => {
    Promise.all([
      examApi.get(id),
      examApi.listQuestions(id),
    ])
      .then(([examData, questionsData]) => {
        setExam(examData.exam)
        setQuestions(questionsData.questions)
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }, [id])

  const addQuestion = () => {
    setQuestions([
      ...questions,
      {
        body: '',
        correctChoiceIndex: 0,
        choices: ['', '', '', ''],
        isNew: true,
      },
    ])
  }

  const updateQuestion = (index, field, value) => {
    const updated = [...questions]
    updated[index][field] = value
    setQuestions(updated)
  }

  const updateChoice = (qIndex, cIndex, value) => {
    const updated = [...questions]
    if (!updated[qIndex].choices) updated[qIndex].choices = []
    updated[qIndex].choices[cIndex] = value
    setQuestions(updated)
  }

  const removeQuestion = async (qIndex) => {
    const q = questions[qIndex]
    if (q.id) {
      if (!confirm('Hapus soal ini?')) return
      try {
        await examApi.deleteQuestion(id, q.id)
        setQuestions(questions.filter((_, i) => i !== qIndex))
      } catch (err) {
        setError(err.message)
      }
    } else {
      setQuestions(questions.filter((_, i) => i !== qIndex))
    }
  }

  const saveAll = async () => {
    setSaving(true)
    setError('')
    try {
      for (const q of questions) {
        if (!q.body.trim()) continue
        const validChoices = q.choices.filter((c) => c.trim())
        if (validChoices.length < 2) continue

        const payload = {
          question: {
            body: q.body,
            correct_choice_index: q.correctChoiceIndex,
          },
          choices: validChoices.map((text) => ({ text })),
        }

        if (q.id) {
          await examApi.updateQuestion(id, q.id, payload)
        } else {
          const res = await examApi.addQuestion(id, payload)
          q.id = res.question_id
        }
      }
      navigate(`/dashboard`)
    } catch (err) {
      setError(err.message)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  if (!exam) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-slate-500 mb-4">Ujian tidak ditemukan</p>
          <Link to="/dashboard" className="text-blue-600 hover:text-blue-700 text-sm">
            ← Kembali ke Dashboard
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <Link to="/dashboard" className="text-sm text-slate-400 hover:text-slate-600 transition">
              ← Dashboard
            </Link>
            <h1 className="text-2xl font-bold text-slate-800 mt-1">{exam.title}</h1>
            {exam.description && (
              <p className="text-slate-500 text-sm mt-1">{exam.description}</p>
            )}
          </div>
          <div className="text-sm text-slate-400">
            {questions.length} soal • {exam.time_limit_minutes} menit
          </div>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
            {error}
          </div>
        )}

        <div className="space-y-4 mb-6">
          {questions.map((q, qIndex) => (
            <div
              key={q.id || qIndex}
              className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm"
            >
              <div className="flex items-start justify-between mb-3">
                <span className="text-xs font-semibold text-slate-400 uppercase tracking-wide">
                  Soal {qIndex + 1}
                </span>
                <button
                  onClick={() => removeQuestion(qIndex)}
                  className="text-slate-300 hover:text-red-500 transition text-sm"
                >
                  Hapus
                </button>
              </div>

              <textarea
                value={q.body}
                onChange={(e) => updateQuestion(qIndex, 'body', e.target.value)}
                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y min-h-[80px]"
                placeholder="Tulis pertanyaan di sini..."
              />

              <div className="mt-3 space-y-2">
                {q.choices.map((choice, cIndex) => (
                  <div key={cIndex} className="flex items-center gap-2">
                    <button
                      onClick={() => updateQuestion(qIndex, 'correctChoiceIndex', cIndex)}
                      className={`w-7 h-7 rounded-full border-2 flex items-center justify-center text-xs font-bold transition shrink-0 ${
                        q.correctChoiceIndex === cIndex
                          ? 'border-green-500 bg-green-500 text-white'
                          : 'border-slate-200 text-slate-400 hover:border-slate-400'
                      }`}
                      title="Jawaban benar"
                    >
                      {LABELS[cIndex]}
                    </button>
                    <input
                      type="text"
                      value={choice}
                      onChange={(e) => updateChoice(qIndex, cIndex, e.target.value)}
                      className="flex-1 border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder={`Pilihan ${LABELS[cIndex]}`}
                    />
                  </div>
                ))}
              </div>

              <p className="text-xs text-slate-400 mt-2">
                Klik huruf untuk tandai jawaban yang benar
              </p>
            </div>
          ))}
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={addQuestion}
            className="border border-dashed border-slate-300 hover:border-slate-500 text-slate-500 hover:text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition w-full"
          >
            + Tambah Soal
          </button>
        </div>

        <div className="flex justify-end mt-6 gap-3">
          <Link
            to="/dashboard"
            className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2"
          >
            Batal
          </Link>
          <button
            onClick={saveAll}
            disabled={saving}
            className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium px-6 py-2 rounded-lg transition"
          >
            {saving ? 'Menyimpan...' : 'Simpan Semua'}
          </button>
        </div>
      </div>
    </div>
  )
}
