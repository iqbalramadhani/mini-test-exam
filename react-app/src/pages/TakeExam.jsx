import { useState, useEffect, useRef, useCallback } from 'react'
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
  }, [timeLeft, submitted, handleAutoSubmit])

  const handleAutoSubmit = useCallback(async () => {
    try {
      await handleSubmit()
    } catch {
      navigate('/exams')
    }
  }, [handleSubmit, navigate])

  const handleSelectAnswer = (questionId, choiceIndex) => {
    setAnswers((prev) => ({ ...prev, [questionId]: choiceIndex }))
  }

  const handleSubmit = useCallback(async () => {
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
  }, [submitted, questions, answers, attemptId, navigate])

  const formatTime = (seconds) => {
    const m = Math.floor(seconds / 60)
    const s = seconds % 60
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
  }

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
          <button onClick={() => navigate('/exams')} className="text-indigo-600 font-medium text-sm hover:underline">
            Kembali
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-transparent">
      <div className="bg-white/80 backdrop-blur-md border-b border-white shadow-sm px-4 py-3 sticky top-0 z-20">
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
                    confirmButtonColor: '#4f46e5',
                  }).then((result) => {
                    if (result.isConfirmed) handleSubmit()
                  })
                }}
                className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-xl shadow-md transition-all"
              >
                Kumpulkan
              </button>
          </div>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex gap-6">
          <div className="hidden md:block w-52 shrink-0">
            <div className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-5 shadow-sm sticky top-24">
              <p className="text-xs font-bold text-slate-400 mb-4 uppercase tracking-wider text-center">Nomor Soal</p>
              <div className="grid grid-cols-5 gap-2">
                {questions.map((q, i) => (
                  <button
                    key={q.id}
                    onClick={() => {
                      document.getElementById(`question-${q.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
                    }}
                    className={`aspect-square rounded-xl text-xs font-bold transition-all ${
                      answers[q.id] !== undefined
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
                        : 'bg-white border border-slate-200 text-slate-500 hover:border-indigo-300'
                    }`}
                  >
                    {i + 1}
                  </button>
                ))}
              </div>
              <div className="mt-5 flex items-center justify-center gap-2 text-xs text-slate-500">
                <div className="w-3 h-3 rounded-md bg-indigo-600"></div>
                <span>Sudah dijawab</span>
              </div>
            </div>
          </div>

          <div className="flex-1 space-y-6">
            {questions.map((q, index) => (
              <div
                key={q.id}
                id={`question-${q.id}`}
                className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-6 shadow-sm"
              >
                <div className="flex items-start gap-4 mb-5">
                  <span className="bg-indigo-100 text-indigo-700 text-sm font-extrabold w-8 h-8 rounded-xl flex items-center justify-center shrink-0">
                    {index + 1}
                  </span>
                  <p className="text-slate-800 text-sm leading-relaxed pt-1.5">{q.body}</p>
                </div>

                <div className="space-y-3 ml-12">
                  {q.choices?.map((choice, ci) => (
                    <button
                      key={choice.id}
                      onClick={() => handleSelectAnswer(q.id, ci)}
                      className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl border text-sm text-left transition-all ${
                        answers[q.id] === ci
                          ? 'border-indigo-500 bg-indigo-50 text-indigo-800 shadow-sm'
                          : 'border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm text-slate-700'
                      }`}
                    >
                      <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors ${
                        answers[q.id] === ci
                          ? 'bg-indigo-600 text-white'
                          : 'bg-slate-100 text-slate-500'
                      }`}>
                        {LABELS[ci]}
                      </span>
                      <span className="flex-1">{choice.text}</span>
                      {answers[q.id] === ci && (
                        <svg className="w-5 h-5 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                      )}
                    </button>
                  ))}
                </div>
              </div>
            ))}

            <div className="flex justify-end pt-4 pb-8">
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
                    confirmButtonColor: '#4f46e5',
                  }).then((result) => {
                    if (result.isConfirmed) handleSubmit()
                  })
                }}
                className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-medium px-8 py-3 rounded-xl shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all"
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
