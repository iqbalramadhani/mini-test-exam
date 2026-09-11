import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { attemptApi } from '../api'
import FormattedText from '../components/FormattedText'

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
      <div className="min-h-screen bg-transparent flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="min-h-screen bg-transparent flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-500 mb-4">{error}</p>
          <Link to="/exams" className="text-indigo-600 font-medium text-sm hover:underline">
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
    <div className="min-h-screen bg-transparent">
      <div className="max-w-3xl mx-auto px-4 py-8">
        <Link to="/exams" className="text-sm text-indigo-600 hover:text-indigo-800 font-medium inline-flex items-center gap-1.5 mb-8 transition-colors">
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Kembali ke Daftar Ujian
        </Link>

        <div className="bg-white/80 backdrop-blur-xl rounded-3xl border border-white shadow-xl p-8 mb-10 text-center relative overflow-hidden">
          <div className="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-gradient-to-br from-indigo-200 to-blue-200 rounded-full blur-3xl opacity-50 pointer-events-none"></div>
          <div className="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-gradient-to-tr from-emerald-200 to-cyan-200 rounded-full blur-3xl opacity-50 pointer-events-none"></div>
          
          <div className="relative z-10">
            <p className="text-sm font-semibold text-indigo-600 mb-2 tracking-wider uppercase">Hasil Ujian</p>
            <div className="text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 mb-4 drop-shadow-sm">
              {score % 1 === 0 ? score : score.toFixed(2)}
              <span className="text-3xl text-slate-400 font-semibold align-baseline"> / {attempt.max_score ?? 100}</span>
            </div>
            <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm mt-6 bg-white/50 inline-flex px-6 py-3 rounded-2xl border border-slate-100">
              <span className="text-emerald-600 font-semibold flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                {correctCount} benar
              </span>
              <span className="text-slate-300">|</span>
              <span className="text-rose-500 font-semibold flex items-center gap-1.5">
                <span className="w-2 h-2 rounded-full bg-rose-500"></span>
                {wrongCount} salah
              </span>
              <span className="text-slate-300">|</span>
              <span className="text-slate-600 font-medium flex items-center gap-1.5">
                ⏱ {durationFormatted}
              </span>
            </div>
            <div className="mt-6">
              <span className={`inline-block px-5 py-2 rounded-full text-sm font-bold shadow-sm ${
                (score / (attempt.max_score ?? 100)) >= 0.7 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500 text-white' : 'bg-gradient-to-r from-amber-400 to-orange-400 text-white'
              }`}>
                {(score / (attempt.max_score ?? 100)) >= 0.7 ? 'Lulus' : 'Tidak Lulus'}
              </span>
            </div>
          </div>
        </div>

        <h2 className="font-extrabold text-slate-800 text-xl mb-6">Review Jawaban</h2>

        <div className="space-y-5">
          {answers.map((ans, index) => (
            <div
              key={ans.answer_id}
              className={`bg-white/80 backdrop-blur-md rounded-2xl border p-6 shadow-sm transition-all hover:shadow-md ${
                ans.is_correct === 1
                  ? 'border-emerald-200 shadow-emerald-100/50'
                  : 'border-rose-200 shadow-rose-100/50'
              }`}
            >
              <div className="flex items-start gap-4 mb-4">
                <span className={`w-8 h-8 rounded-xl flex items-center justify-center text-sm font-extrabold shrink-0 shadow-sm ${
                  ans.is_correct === 1 ? 'bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-800' : 'bg-gradient-to-br from-rose-100 to-rose-200 text-rose-800'
                }`}>
                  {index + 1}
                </span>
                <FormattedText className="text-slate-800 text-sm leading-relaxed flex-1 pt-1.5 font-medium">{ans.question_body}</FormattedText>
              </div>

              <div className="ml-12 space-y-2.5 text-sm">
                <div className={`px-4 py-3 rounded-xl border ${
                  ans.is_correct === 1 ? 'bg-emerald-50/80 border-emerald-100 text-emerald-800' : 'bg-rose-50/80 border-rose-100 text-rose-800'
                }`}>
                  <span className="font-bold opacity-80">Jawabanmu: </span>
                  {ans.selected_choice_index >= 0 ? (
                    <span className="inline-flex gap-1">
                      {LABELS[ans.selected_choice_index]}. <FormattedText>{ans.selected_text || '—'}</FormattedText>
                    </span>
                  ) : (
                    '(Tidak dijawab)'
                  )}
                  <span className="ml-2 font-bold">{ans.is_correct === 1 ? '✓' : '✗'}</span>
                </div>
                {ans.is_correct === 0 && (
                  <div className="px-4 py-3 rounded-xl bg-emerald-50/80 border border-emerald-100 text-emerald-800">
                    <span className="font-bold opacity-80">Kunci Jawaban: </span>
                    <span className="inline-flex gap-1">
                      {LABELS[ans.correct_choice_index]}. <FormattedText>{ans.correct_text || '—'}</FormattedText>
                    </span>
                  </div>
                )}
                {ans.explanation && (
                  <div className="mt-3 px-4 py-3 rounded-xl bg-indigo-50/50 border border-indigo-100/50 text-indigo-900 text-sm leading-relaxed">
                    <span className="font-bold text-indigo-700">Pembahasan: </span>
                    <FormattedText>{ans.explanation}</FormattedText>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-10 text-center pb-8">
          <Link
            to={`/take/${attempt.exam_id}`}
            className="inline-block bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-semibold px-8 py-3.5 rounded-full shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all"
          >
            Ulangi Ujian
          </Link>
        </div>
      </div>
    </div>
  )
}
