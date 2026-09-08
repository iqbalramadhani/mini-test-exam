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
  const [showImport, setShowImport] = useState(false)
  const [importText, setImportText] = useState('')
  const [parsedQuestions, setParsedQuestions] = useState([])
  const [parseError, setParseError] = useState('')

  const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

  const padChoices = (choices) => {
    const arr = choices.map((c) => c.trim()).filter(Boolean)
    while (arr.length < 5) arr.push('')
    return arr
  }

  const parseQuestionsFromText = (text) => {
    const lines = text.split('\n').map((l) => l.trimEnd())
    const questions = []
    let current = null
    let phase = 'question' // question | choices | answer | explanation
    let explanationLines = []

    const finishQuestion = () => {
      if (!current || !current.body.trim()) return
      const answerLabel = current.answer?.trim().toUpperCase()
      const answerIndex = answerLabel ? LABELS.indexOf(answerLabel) : 0
      if (answerIndex === -1) return
      questions.push({
        body: current.body,
        correctChoiceIndex: answerIndex,
        choices: padChoices(current.choices),
        questionType: 'choice',
        explanation: explanationLines.join('\n').trim(),
        keterangan: '',
      })
    }

    for (const line of lines) {
      const upper = line.toUpperCase()

      if (upper.startsWith('NOMOR') && upper.includes('SOAL:')) {
        finishQuestion()
        current = { body: '', choices: [], answer: '' }
        explanationLines = []
        phase = 'question'
        current.body = line.replace(/^NOMOR\s*\d+\s*SOAL:\s*/i, '').trim()
        continue
      }

      if (upper.startsWith('SOAL:')) {
        finishQuestion()
        current = { body: '', choices: [], answer: '' }
        explanationLines = []
        phase = 'question'
        current.body = line.replace(/^SOAL:\s*/i, '').trim()
        continue
      }

      if (upper === 'SOAL' || upper === 'NOMOR') continue

      if (upper.startsWith('PEMBHASAN:')) {
        if (current) {
          finishQuestion()
        }
        phase = 'explanation'
        explanationLines = [line.replace(/^PEMBHASAN:\s*/i, '').trim()]
        continue
      }

      if (upper.startsWith('KUNCI JAWABAN:') || upper === 'JAWABAN:') {
        if (current) {
          current.answer = line.replace(/^(KUNCI\s+)?JAWABAN:\s*/i, '').trim()
        }
        phase = 'answer'
        continue
      }

      if (upper === 'JAWABAN BENAR:' || upper.startsWith('KEY ANSWER:')) {
        if (current) {
          current.answer = line.replace(/^(JAWABAN\s+BENAR|KEY\s+ANSWER):\s*/i, '').trim()
        }
        phase = 'answer'
        continue
      }

      if (upper.startsWith('PILIHAN JAWABAN:') || upper === 'OPTIONS:' || upper === 'CHOICES:') {
        phase = 'choices'
        continue
      }

      if (phase === 'question' && current) {
        current.body += (current.body ? ' ' : '') + line
        continue
      }

      if (phase === 'choices' && current) {
        const match = line.match(/^([A-F])\.\s*(.+)/i)
        if (match) {
          const idx = LABELS.indexOf(match[1].toUpperCase())
          if (idx >= 0 && idx < current.choices.length) {
            current.choices[idx] = match[2].trim()
          } else {
            current.choices[idx] = match[2].trim()
          }
          continue
        }
        if (line) {
          const lastIdx = current.choices.length - 1
          if (lastIdx >= 0) current.choices[lastIdx] = (current.choices[lastIdx] || '') + ' ' + line
        }
        continue
      }

      if (phase === 'explanation') {
        explanationLines.push(line)
      }
    }

    finishQuestion()
    return questions
  }

  const handleParse = () => {
    setParseError('')
    const parsed = parseQuestionsFromText(importText)
    if (parsed.length === 0) {
      setParseError('Tidak ada soal yang berhasil diparse. Periksa format teks.')
      setParsedQuestions([])
      return
    }
    setParsedQuestions(parsed)
  }

  const handleImport = () => {
    if (parsedQuestions.length === 0) return
    setQuestions([
      ...questions,
      ...parsedQuestions.map((q) => ({ ...q, isNew: true })),
    ])
    setShowImport(false)
    setImportText('')
    setParsedQuestions([])
    setParseError('')
  }

  const handleCloseImport = () => {
    setShowImport(false)
    setImportText('')
    setParsedQuestions([])
    setParseError('')
  }

  useEffect(() => {
    Promise.all([
      examApi.get(id),
      examApi.listQuestions(id),
    ])
      .then(([examData, questionsData]) => {
        setExam(examData.exam)
        const normalized = (questionsData.questions || []).map((q) => {
          const loadedChoices = q.choices.map((c) => c.text)
          while (loadedChoices.length < 5) loadedChoices.push('')
          return {
            ...q,
            choices: loadedChoices,
            correctChoiceIndex: q.correct_choice_index ?? 0,
            questionType: q.question_type || 'choice',
            explanation: q.explanation || '',
            keterangan: q.keterangan || '',
          }
        })
        setQuestions(normalized)
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
        choices: ['', '', '', '', ''],
        questionType: 'choice',
        explanation: '',
        keterangan: '',
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

        const payload = {
          question: {
            body: q.body,
            correct_choice_index: q.correctChoiceIndex ?? 0,
            question_type: q.questionType || 'choice',
            explanation: q.explanation || '',
            keterangan: q.keterangan || '',
          },
          choices: [],
        }

        if (q.questionType === 'choice' || q.questionType === 'multiple') {
          const validChoices = (q.choices || []).filter((c) => typeof c === 'string' && c.trim())
          if (validChoices.length < 2) continue
          payload.choices = validChoices.map((text) => ({ text }))
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

              <div className="flex gap-3 mb-3">
                <select
                  value={q.questionType || 'choice'}
                  onChange={(e) => updateQuestion(qIndex, 'questionType', e.target.value)}
                  className="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="choice">Single Choice</option>
                  <option value="multiple">Multiple Choice</option>
                  <option value="fill">Fill-in</option>
                  <option value="essay">Essay</option>
                </select>
                <textarea
                  value={q.body}
                  onChange={(e) => updateQuestion(qIndex, 'body', e.target.value)}
                  className="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y min-h-[80px]"
                  placeholder="Tulis pertanyaan di sini..."
                />
              </div>

              {(q.questionType === 'choice' || q.questionType === 'multiple') && (
                <>
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
                </>
              )}

              <textarea
                value={q.explanation || ''}
                onChange={(e) => updateQuestion(qIndex, 'explanation', e.target.value)}
                className="w-full mt-3 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y min-h-[60px]"
                placeholder="Explanation (optional)..."
              />

              <textarea
                value={q.keterangan || ''}
                onChange={(e) => updateQuestion(qIndex, 'keterangan', e.target.value)}
                className="w-full mt-3 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y min-h-[60px]"
                placeholder="Notes / additional information..."
              />
            </div>
          ))}
        </div>

        <div className="flex items-center gap-3 mb-6">
          <button
            onClick={addQuestion}
            className="border border-dashed border-slate-300 hover:border-slate-500 text-slate-500 hover:text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition w-full"
          >
            + Tambah Soal
          </button>
          <button
            onClick={() => setShowImport(true)}
            className="border border-dashed border-blue-300 hover:border-blue-500 text-blue-500 hover:text-blue-700 text-sm font-medium px-4 py-2.5 rounded-lg transition w-full"
          >
            + Import Soal
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

      {showImport && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 rounded-t-xl flex items-center justify-between">
              <h2 className="text-lg font-bold text-slate-800">Import Soal</h2>
              <button
                onClick={handleCloseImport}
                className="text-slate-400 hover:text-slate-600 text-xl leading-none"
              >
                ×
              </button>
            </div>

            <div className="p-6 space-y-4">
              <div>
                <p className="text-xs text-slate-500 mb-2">
                  Tempel teks soal di bawah. Format yang didukung:
                </p>
                <pre className="text-xs text-slate-400 bg-slate-50 rounded-lg p-3 mb-3 whitespace-pre-wrap font-mono">
{`Nomor 1 Soal: Pertanyaan di sini
Pilihan Jawaban:
  A. Opsi pertama
  B. Opsi kedua
  C. Opsi ketiga
  D. Opsi keempat
  E. Opsi kelima
Kunci Jawaban: A
Pembahasan: Penjelasan soal ini`}
                </pre>
                <textarea
                  value={importText}
                  onChange={(e) => setImportText(e.target.value)}
                  rows={12}
                  className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y font-mono"
                  placeholder="Tempel teks soal di sini..."
                />
              </div>

              {parseError && (
                <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                  {parseError}
                </div>
              )}

              {parsedQuestions.length > 0 && (
                <div>
                  <p className="text-sm font-semibold text-slate-700 mb-3">
                    {parsedQuestions.length} soal ditemukan:
                  </p>
                  <div className="space-y-3 max-h-60 overflow-y-auto border border-slate-200 rounded-lg p-3 bg-slate-50">
                    {parsedQuestions.map((q, i) => (
                      <div key={i} className="bg-white rounded-lg border border-slate-200 p-3 text-sm">
                        <p className="font-medium text-slate-800 mb-1">
                          {i + 1}. {q.body}
                        </p>
                        <div className="space-y-0.5 text-slate-600">
                          {q.choices.map((c, ci) => (
                            <p key={ci} className={q.correctChoiceIndex === ci ? 'text-green-600 font-semibold' : ''}>
                              {LABELS[ci]}. {c}
                              {q.correctChoiceIndex === ci && ' ✓'}
                            </p>
                          ))}
                        </div>
                        {q.explanation && (
                          <p className="text-xs text-slate-400 mt-1">
                            Pembahasan: {q.explanation}
                          </p>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <div className="sticky bottom-0 bg-white border-t border-slate-200 px-6 py-4 rounded-b-xl flex justify-end gap-3">
              <button
                onClick={handleCloseImport}
                className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2"
              >
                Batal
              </button>
              <button
                onClick={handleParse}
                className="text-sm text-slate-600 border border-slate-300 hover:border-slate-500 px-4 py-2 rounded-lg transition"
              >
                Parse Teks
              </button>
              <button
                onClick={handleImport}
                disabled={parsedQuestions.length === 0}
                className="text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-medium px-4 py-2 rounded-lg transition"
              >
                Import {parsedQuestions.length > 0 ? `(${parsedQuestions.length} soal)` : ''}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
