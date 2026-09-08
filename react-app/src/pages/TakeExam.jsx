import { useState, useEffect, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import Swal from 'sweetalert2'
import { attemptApi } from '../api'

const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

export default function TakeExam() {
  const { id } = useParams()
  const navigate = useNavigate()
  const timerRef = useRef(null)

  const [exam, setExam] = useState(null)
  const [questions, setQuestions] = useState([])
  const [answers, setAnswers] = useState({})
  const [attemptId, setAttemptId] = useState(null)
  const [timeLeft, setTimeLeft] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [submitted, setSubmitted] = useState(false)

  useEffect(() => {
    attemptApi.start(parseInt(id))
      .then((data) => {
        setExam(data.exam)
        setQuestions(data.questions)
        setAttemptId(data.attempt_id)
        const totalSeconds = (data.exam.time_limit_minutes || 60) * 60
        setTimeLeft(totalSeconds)
        setLoading(false)
      })
      .catch((err) => {
        setError(err.message)
        setLoading(false)
      })
  }, [id])

  useEffect(() => {
    if (timeLeft === null || timeLeft <= 0 || submitted) return

    timerRef.current = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          clearInterval(timerRef.current)
          handleAutoSubmit()
          return 0
        }
        return prev - 1
      })
    }, 1000)

    return () => clearInterval(timerRef.current)
  }, [timeLeft, submitted])

  const handleAutoSubmit = async () => {
    try {
      await handleSubmit()
    } catch {
      navigate('/exams')
    }
  }

  const handleSelectAnswer = (questionId, choiceIndex) => {
    setAnswers((prev) => ({ ...prev, [questionId]: choiceIndex }))
  }

  const handleSubmit = async () => {
    if (submitted) return
    setSubmitted(true)

    const answerList = questions.map((q) => ({
      question_id: q.id,
      selected_choice_index: answers[q.id] ?? -1,
    }))

    try {
      const result = await attemptApi.submit(attemptId, answerList)
      Swal.fire({
        title: 'Ujian Selesai!',
        html: `Skor kamu: <strong>${result.score}</strong> / 100<br><span class="text-sm text-slate-500">${result.correct} dari ${result.total} soal benar</span>`,
        icon: result.score >= 70 ? 'success' : 'info',
        confirmButtonText: 'Lihat Hasil',
      }).then(() => {
        navigate(`/result/${attemptId}`)
      })
    } catch (err) {
      setError(err.message)
      setSubmitted(false)
    }
  }

  const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
  }

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
          <button onClick={() => navigate('/exams')} className="text-blue-600 text-sm hover:underline">
            Kembali
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="bg-white border-b border-slate-200 px-4 py-3 sticky top-0 z-10">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <div>
            <h1 className="font-semibold text-slate-800 text-sm">{exam?.title}</h1>
            <p className="text-xs text-slate-400 mt-0.5">{questions.length} soal</p>
          </div>
          <div className="flex items-center gap-4">
            <div className={`text-lg font-mono font-bold ${timeLeft !== null && timeLeft < 60 ? 'text-red-500' : 'text-slate-700'}`}>
              {formatTime(timeLeft ?? 0)}
            </div>
            <button
              onClick={() => {
                const unanswered = questions.filter((q) => answers[q.id] === undefined).length
                Swal.fire({
                  title: 'Kumpulkan Jawaban?',
                  text: unanswered > 0
                    ? `Masih ada ${unanswered} soal belum dijawab.`
                    : 'Yakin ingin mengumpulkan jawaban?',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: 'Ya, Kumpulkan',
                  cancelButtonText: 'Lanjut Ujian',
                  confirmButtonColor: '#2563eb',
                }).then((result) => {
                  if (result.isConfirmed) handleSubmit()
                })
              }}
              className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
            >
              Kumpulkan
            </button>
          </div>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 py-6">
        <div className="flex gap-4">
          <div className="hidden md:block w-48 shrink-0">
            <div className="bg-white rounded-xl border border-slate-200 p-4 sticky top-24">
              <p className="text-xs font-semibold text-slate-500 mb-3 uppercase tracking-wide">Nomor Soal</p>
              <div className="grid grid-cols-5 gap-1.5">
                {questions.map((q, i) => (
                  <button
                    key={q.id}
                    onClick={() => {
                      document.getElementById(`question-${q.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
                    }}
                    className={`aspect-square rounded-lg text-xs font-bold transition ${
                      answers[q.id] !== undefined
                        ? 'bg-blue-600 text-white'
                        : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                    }`}
                  >
                    {i + 1}
                  </button>
                ))}
              </div>
              <div className="mt-4 flex items-center gap-2 text-xs text-slate-400">
                <div className="w-3 h-3 rounded bg-blue-600"></div>
                <span>Sudah dijawab</span>
              </div>
            </div>
          </div>

          <div className="flex-1 space-y-4">
            {questions.map((q, index) => (
              <div
                key={q.id}
                id={`question-${q.id}`}
                className="bg-white rounded-xl border border-slate-200 p-5"
              >
                <div className="flex items-start gap-3 mb-4">
                  <span className="bg-blue-100 text-blue-700 text-sm font-bold w-7 h-7 rounded-lg flex items-center justify-center shrink-0">
                    {index + 1}
                  </span>
                  <p className="text-slate-800 text-sm leading-relaxed pt-1">{q.body}</p>
                </div>

                <div className="space-y-2 ml-10">
                  {q.choices?.map((choice, ci) => (
                    <button
                      key={choice.id}
                      onClick={() => handleSelectAnswer(q.id, ci)}
                      className={`w-full flex items-center gap-3 px-4 py-2.5 rounded-lg border text-sm text-left transition ${
                        answers[q.id] === ci
                          ? 'border-blue-500 bg-blue-50 text-blue-700'
                          : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700'
                      }`}
                    >
                      <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                        answers[q.id] === ci
                          ? 'bg-blue-600 text-white'
                          : 'bg-slate-100 text-slate-500'
                      }`}>
                        {LABELS[ci]}
                      </span>
                      <span className="flex-1">{choice.text}</span>
                      {answers[q.id] === ci && (
                        <svg className="w-4 h-4 text-blue-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      )}
                    </button>
                  ))}
                </div>
              </div>
            ))}

            <div className="flex justify-end pt-2 pb-8">
              <button
                onClick={() => {
                  const unanswered = questions.filter((q) => answers[q.id] === undefined).length
                  Swal.fire({
                    title: 'Kumpulkan Jawaban?',
                    text: unanswered > 0
                      ? `Masih ada ${unanswered} soal belum dijawab.`
                      : 'Yakin ingin mengumpulkan jawaban?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Kumpulkan',
                    cancelButtonText: 'Lanjut Ujian',
                    confirmButtonColor: '#2563eb',
                  }).then((result) => {
                    if (result.isConfirmed) handleSubmit()
                  })
                }}
                className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition"
              >
                Kumpulkan Jawaban
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
