import { useState, useEffect, useRef, useCallback } from 'react'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import Swal from 'sweetalert2'
import { attemptApi } from '../api'
import FormattedText from '../components/FormattedText'

const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

export default function TakeExam() {
  const { id } = useParams()
  const navigate = useNavigate()
  const timerRef = useRef(null)

  const [searchParams] = useSearchParams()
  const initialMode = searchParams.get('mode') || 'tryout'

  const [exam, setExam] = useState(null)
  const [examMode, setExamMode] = useState('tryout')
  const [questions, setQuestions] = useState([])
  const [answers, setAnswers] = useState({})
  const [attemptId, setAttemptId] = useState(null)
  const [timeLeft, setTimeLeft] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [submitted, setSubmitted] = useState(false)
  const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0)

  useEffect(() => {
    attemptApi.start(parseInt(id), { mode: initialMode })
      .then((data) => {
        setExam(data.exam)
        setQuestions(data.questions)
        setAttemptId(data.attempt_id)
        setExamMode(data.mode || 'tryout')
        
        if (data.mode === 'practice') {
          setTimeLeft(null)
        } else {
          const totalSeconds = (data.exam.time_limit_minutes || 60) * 60
          setTimeLeft(totalSeconds)
        }
        
        setLoading(false)
      })
      .catch((err) => {
        setError(err.message)
        setLoading(false)
      })
  }, [id])



  const handleSelectAnswer = (questionId, choiceIndex) => {
    if (examMode === 'practice' && answers[questionId] !== undefined) {
      return // Lock answer in practice mode
    }
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

  const handleAutoSubmit = useCallback(async () => {
    try {
      await handleSubmit()
    } catch {
      navigate('/exams')
    }
  }, [handleSubmit, navigate])

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
            <div className={`text-lg font-mono font-bold ${examMode === 'practice' ? 'text-indigo-600 text-sm bg-indigo-50 px-3 py-1.5 rounded-lg' : timeLeft !== null && timeLeft < 60 ? 'text-red-500' : 'text-slate-700'}`}>
              {examMode === 'practice' ? 'Mode Latihan' : formatTime(timeLeft ?? 0)}
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
        <div className="flex flex-col md:flex-row gap-6">
          {/* Mobile Navigation */}
          <div className="md:hidden w-full mb-6">
            <div className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-4 shadow-sm">
              <p className="text-xs font-bold text-slate-400 mb-3 uppercase tracking-wider">Navigasi Soal</p>
              <div className="flex gap-2 overflow-x-auto pb-2 snap-x">
                {questions.map((q, i) => (
                  <button
                    key={q.id}
                    onClick={() => setCurrentQuestionIndex(i)}
                    className={`w-10 h-10 shrink-0 snap-center rounded-xl text-xs font-bold transition-all flex items-center justify-center ${
                      answers[q.id] !== undefined
                        ? currentQuestionIndex === i
                          ? 'bg-indigo-700 text-white shadow-md shadow-indigo-300 ring-2 ring-indigo-200 ring-offset-1'
                          : 'bg-indigo-500 text-white shadow-sm'
                        : currentQuestionIndex === i
                          ? 'bg-white border-2 border-indigo-400 text-indigo-600 shadow-sm'
                          : 'bg-white border border-slate-200 text-slate-500 hover:border-indigo-300'
                    }`}
                  >
                    {i + 1}
                  </button>
                ))}
              </div>
            </div>
          </div>

          <div className="hidden md:block w-52 shrink-0">
            <div className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-5 shadow-sm sticky top-24 max-h-[calc(100vh-8rem)] overflow-y-auto">
              <p className="text-xs font-bold text-slate-400 mb-4 uppercase tracking-wider text-center">Nomor Soal</p>
              <div className="grid grid-cols-5 gap-2">
                {questions.map((q, i) => (
                  <button
                    key={q.id}
                    onClick={() => setCurrentQuestionIndex(i)}
                    className={`aspect-square flex items-center justify-center rounded-xl text-xs font-bold transition-all ${
                      answers[q.id] !== undefined
                        ? currentQuestionIndex === i
                          ? 'bg-indigo-700 text-white shadow-md shadow-indigo-300 ring-2 ring-indigo-200 ring-offset-1'
                          : 'bg-indigo-500 text-white shadow-sm'
                        : currentQuestionIndex === i
                          ? 'bg-white border-2 border-indigo-400 text-indigo-600 shadow-sm'
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
            {questions.length > 0 && (() => {
              const q = questions[currentQuestionIndex]
              return (
              <div
                key={q.id}
                className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-6 shadow-sm min-h-[400px] flex flex-col"
              >
                <div className="flex items-start gap-4 mb-6">
                  <span className="bg-indigo-100 text-indigo-700 text-sm font-extrabold w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                    {currentQuestionIndex + 1}
                  </span>
                  <div className="flex-1 pt-1.5">
                    <FormattedText className="text-slate-800 text-[15px] leading-relaxed">{q.body}</FormattedText>
                  </div>
                </div>

                <div className="space-y-3 ml-13 mb-8 flex-1 pl-1">
                  {q.choices?.map((choice, ci) => {
                    const isSelected = answers[q.id] === ci
                    let buttonClass = ''
                    let labelClass = ''
                    
                    if (examMode === 'practice' && answers[q.id] !== undefined) {
                      const isCorrectChoice = q.correct_choice_index == ci
                      if (isCorrectChoice) {
                        buttonClass = 'border-green-500 bg-green-50/80 text-green-800 shadow-sm ring-1 ring-green-200 z-10'
                        labelClass = 'bg-green-600 text-white shadow-sm'
                      } else if (isSelected) {
                        buttonClass = 'border-red-500 bg-red-50/80 text-red-800 shadow-sm ring-1 ring-red-200'
                        labelClass = 'bg-red-600 text-white shadow-sm'
                      } else {
                        buttonClass = 'border-slate-200 bg-white text-slate-400 opacity-60 cursor-not-allowed'
                        labelClass = 'bg-slate-100 text-slate-400 border border-slate-200'
                      }
                    } else {
                      buttonClass = isSelected
                        ? 'border-indigo-500 bg-indigo-50/80 text-indigo-800 shadow-sm ring-1 ring-indigo-200'
                        : 'border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm text-slate-700'
                      labelClass = isSelected
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-500 border border-slate-200'
                    }

                    return (
                      <button
                        key={choice.id}
                        onClick={() => handleSelectAnswer(q.id, ci)}
                        className={`w-full flex items-center gap-4 px-4 py-3.5 rounded-xl border text-sm text-left transition-all ${buttonClass}`}
                      >
                        <span className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 transition-colors ${labelClass}`}>
                          {LABELS[ci]}
                        </span>
                        <span className="flex-1 text-[15px]"><FormattedText>{choice.text}</FormattedText></span>
                        {isSelected && examMode !== 'practice' && (
                          <svg className="w-5 h-5 text-indigo-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                          </svg>
                        )}
                        {examMode === 'practice' && answers[q.id] !== undefined && q.correct_choice_index == ci && (
                          <svg className="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                          </svg>
                        )}
                        {examMode === 'practice' && answers[q.id] !== undefined && isSelected && q.correct_choice_index != ci && (
                          <svg className="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        )}
                      </button>
                    )
                  })}
                </div>

                {examMode === 'practice' && answers[q.id] !== undefined && (q.explanation || q.keterangan) && (
                  <div className="ml-13 mb-8 bg-blue-50/50 border border-blue-100 rounded-xl p-5">
                    <h4 className="text-xs font-bold text-blue-600 uppercase tracking-wider mb-2">Pembahasan</h4>
                    <div className="text-sm text-slate-700">
                      {q.explanation && <FormattedText>{q.explanation}</FormattedText>}
                      {q.keterangan && <div className="mt-2 text-slate-600 bg-white/60 p-3 rounded-lg border border-slate-100 text-sm whitespace-pre-wrap">{q.keterangan}</div>}
                    </div>
                  </div>
                )}

                {/* Pagination Controls */}
                <div className="flex items-center justify-between border-t border-slate-100 pt-6 mt-auto">
                  <button
                    onClick={() => setCurrentQuestionIndex(p => Math.max(0, p - 1))}
                    disabled={currentQuestionIndex === 0}
                    className="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                  >
                    ← Sebelumnya
                  </button>
                  
                  {currentQuestionIndex < questions.length - 1 ? (
                    <button
                      onClick={() => setCurrentQuestionIndex(p => Math.min(questions.length - 1, p + 1))}
                      className="px-6 py-2.5 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 hover:bg-indigo-100 text-sm font-bold transition"
                    >
                      Selanjutnya →
                    </button>
                  ) : (
                    <button
                      onClick={() => {
                        const unanswered = questions.filter((qu) => answers[qu.id] === undefined).length
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
                      className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-bold px-8 py-2.5 rounded-xl shadow-md transition-all"
                    >
                      Kumpulkan Jawaban
                    </button>
                  )}
                </div>
              </div>
              )
            })()}
          </div>
        </div>
      </div>
    </div>
  )
}
