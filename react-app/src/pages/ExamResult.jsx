import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { attemptApi } from '../api'

const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

export default function ExamResult() {
  const { attemptId } = useParams()
  const [attempt, setAttempt] = useState(null)
  const [answers, setAnswers] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    attemptApi.getResult(parseInt(attemptId))
      .then((data) => {
        setAttempt(data.attempt)
        setAnswers(data.answers)
        setLoading(false)
      })
      .catch((err) => {
        setError(err.message)
        setLoading(false)
      })
  }, [attemptId])

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-500 mb-4">{error}</p>
          <Link to="/exams" className="text-blue-600 text-sm hover:underline">
            Kembali
          </Link>
        </div>
      </div>
    )
  }

  const correctCount = answers.filter((a) => a.is_correct === 1).length
  const wrongCount = answers.filter((a) => a.is_correct === 0).length
  const score = parseFloat(attempt.score) || 0
  const durationMin = attempt.duration_minutes || 0
  const durationFormatted = `${Math.floor(durationMin)} menit ${Math.round((durationMin % 1) * 60)} detik`

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-3xl mx-auto px-4 py-8">
        <Link to="/exams" className="text-sm text-slate-500 hover:text-slate-700 inline-block mb-6">
          ← Kembali ke Daftar Ujian
        </Link>

        <div className="bg-white rounded-xl border border-slate-200 p-6 mb-6 text-center">
          <p className="text-sm text-slate-500 mb-1">Hasil Ujian</p>
          <div className="text-5xl font-bold text-slate-800 mb-2">
            {score.toFixed(2)}
            <span className="text-2xl text-slate-400 font-normal"> / 100</span>
          </div>
          <div className="flex items-center justify-center gap-6 text-sm mt-4">
            <span className="text-green-600 font-medium">✓ {correctCount} benar</span>
            <span className="text-slate-300">|</span>
            <span className="text-red-500 font-medium">✗ {wrongCount} salah</span>
            <span className="text-slate-300">|</span>
            <span className="text-slate-500">⏱ {durationFormatted}</span>
          </div>
          <div className={`mt-4 inline-block px-4 py-1.5 rounded-full text-sm font-semibold ${
            score >= 70 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
          }`}>
            {score >= 70 ? 'Lulus' : 'Tidak Lulus'}
          </div>
        </div>

        <h2 className="font-semibold text-slate-800 text-lg mb-4">Review Jawaban</h2>

        <div className="space-y-4">
          {answers.map((ans, index) => (
            <div
              key={ans.answer_id}
              className={`bg-white rounded-xl border p-5 ${
                ans.is_correct === 1
                  ? 'border-green-200'
                  : 'border-red-200'
              }`}
            >
              <div className="flex items-start gap-3 mb-3">
                <span className={`w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold shrink-0 ${
                  ans.is_correct === 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                }`}>
                  {index + 1}
                </span>
                <p className="text-slate-800 text-sm leading-relaxed flex-1">{ans.question_body}</p>
              </div>

              <div className="ml-10 space-y-1.5 text-sm">
                <div className={`px-3 py-2 rounded-lg ${
                  ans.is_correct === 1 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
                }`}>
                  <span className="font-semibold">Jawabanmu: </span>
                  {ans.selected_choice_index >= 0
                    ? `${LABELS[ans.selected_choice_index]}. ${ans.selected_text || '—'}`
                    : '(Tidak dijawab)'}
                  <span className="ml-2">{ans.is_correct === 1 ? '✓' : '✗'}</span>
                </div>
                {ans.is_correct === 0 && (
                  <div className="px-3 py-2 rounded-lg bg-green-50 text-green-700">
                    <span className="font-semibold">Kunci Jawaban: </span>
                    {LABELS[ans.correct_choice_index]}. {ans.correct_text || '—'}
                  </div>
                )}
                {ans.explanation && (
                  <div className="mt-2 px-3 py-2 rounded-lg bg-slate-50 text-slate-600 text-xs">
                    <span className="font-semibold">Pembahasan: </span>
                    {ans.explanation}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-8 text-center">
          <Link
            to={`/take/${attempt.exam_id}`}
            className="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition"
          >
            Ulangi Ujian
          </Link>
        </div>
      </div>
    </div>
  )
}
