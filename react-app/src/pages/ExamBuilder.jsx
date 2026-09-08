import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import Swal from 'sweetalert2'
import { examApi } from '../api'

export default function ExamBuilder() {
  const { id } = useParams()
  const [exam, setExam] = useState(null)
  const [questions, setQuestions] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [currentPage, setCurrentPage] = useState(1)
  const ITEMS_PER_PAGE = 10
  const [showImport, setShowImport] = useState(false)
  const [importText, setImportText] = useState('')
  const [parsedQuestions, setParsedQuestions] = useState([])
  const [parseError, setParseError] = useState('')
  const [unparsedLines, setUnparsedLines] = useState([])

  const LABELS = ['A', 'B', 'C', 'D', 'E', 'F']

  const padChoices = (choices) => {
    const arr = choices.map((c) => c.trim()).filter(Boolean)
    while (arr.length < 5) arr.push('')
    return arr
  }

  const parseQuestionsFromText = (text) => {
    const lines = text.split('\n').map((l) => l.trim())
    const questions = []
    const unparsedLines = []
    let current = null
    let phase = 'question' // question | choices | answer | explanation
    let explanationLines = []

    const isSectionKeyword = (upper) =>
      upper.startsWith('NOMOR') ||
      upper.startsWith('SOAL:') ||
      /^NOMOR\s*\d+$/.test(upper) ||
      upper === 'SOAL' ||
      upper.startsWith('PEMBAHASAN:') ||
      /^([Kk]unci|[Jj]awaban)[^\w]*([Jj]awaban|[Bb]enar)?[^\w]*:/i.test(upper) ||
      upper.startsWith('OPTIONS:') ||
      upper.startsWith('CHOICES:') ||
      upper.startsWith('PILIHAN')

    const finishQuestion = () => {
      if (!current || !current.body.trim()) return
      const answerLabel = current.answer?.trim().toUpperCase()
        .replace(/\s+/g, '')
        .replace(/JAWABAN/g, '')
        .replace(/KUNCI/g, '')
        .replace(/BENAR/g, '')
        .replace(/KEY/g, '')
        .replace(/ANSWER/g, '')
      const answerIndex = answerLabel ? LABELS.indexOf(answerLabel) : 0
      if (answerIndex === -1) return
      questions.push({
        body: current.body,
        correctChoiceIndex: answerIndex,
        choices: padChoices(current.choices),
        explanation: explanationLines.join('\n').trim(),
        keterangan: '',
      })
    }

    for (const line of lines) {
      if (!line) continue
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

      if (/^NOMOR\s*\d+$/.test(upper)) {
        finishQuestion()
        current = { body: '', choices: [], answer: '' }
        explanationLines = []
        phase = 'question'
        continue
      }

      if (upper === 'SOAL') continue

      if (upper.startsWith('PEMBAHASAN:')) {
        phase = 'explanation'
        explanationLines = [line.replace(/^PEMBAHASAN:\s*/i, '').trim()]
        continue
      }

      const hasAnswerKeyword = (pattern) => upper.replace(/\s+/g, '').includes(pattern)
      if (hasAnswerKeyword('KUNCIJAWABAN:') || upper === 'JAWABAN:') {
        if (current) {
          current.answer = line.replace(/^(KUNCI\s*)?JAWABAN:\s*/i, '').trim()
        }
        phase = 'answer'
        continue
      }

      if (hasAnswerKeyword('JAWABANBENAR:') || hasAnswerKeyword('KEYANSWER:')) {
        if (current) {
          current.answer = line.replace(/^(JAWABAN\s*BENAR|KEY\s*ANSWER):\s*/i, '').trim()
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
        const match = line.match(/^\s*([A-F])\.\s*(.+)/i)
        if (match) {
          const idx = LABELS.indexOf(match[1].toUpperCase())
          if (idx >= 0) {
            while (current.choices.length <= idx) current.choices.push('')
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
        continue
      }

      if (!isSectionKeyword(upper)) {
        unparsedLines.push(line)
      }
    }

    finishQuestion()
    return { questions, unparsedLines }
  }

  const handleParse = () => {
    setParseError('')
    const { questions, unparsedLines: badLines } = parseQuestionsFromText(importText)
    setUnparsedLines(badLines)
    if (questions.length === 0) {
      const hint = badLines.length > 0
        ? `Format tidak dikenali. Baris yang tidak terbaca: "${badLines[0].substring(0, 60)}"\n\nFormat yang didukung:\n  Nomor X Soal: pertanyaan\n  Pilihan Jawaban:\n    A. opsi...\n    B. opsi...\n  Kunci Jawaban: A`
        : 'Tidak ada soal yang berhasil diparse. Periksa format teks.'
      setParseError(hint)
      setParsedQuestions([])
      return
    }
    setParsedQuestions(questions)
  }

  const handleImport = async () => {
    if (parsedQuestions.length === 0) return
    setSaving(true)
    try {
      const payload = {
        questions: parsedQuestions.map((q) => ({
          body: q.body,
          correct_choice_index: q.correctChoiceIndex ?? 0,
          question_type: 'choice',
          explanation: q.explanation || '',
          keterangan: '',
          choices: (q.choices || []).filter((c) => typeof c === 'string' && c.trim()),
        })),
      }
      const res = await examApi.addQuestionsBulk(id, payload)
      const saved = res.question_ids.map((qid, i) => ({ ...parsedQuestions[i], id: qid }))
      setQuestions([...questions, ...saved])
      setShowImport(false)
      setImportText('')
      setParsedQuestions([])
      setParseError('')
      setUnparsedLines([])
      Swal.fire({ icon: 'success', title: `${saved.length} soal berhasil disimpan`, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Gagal menyimpan soal import', text: err.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true })
    } finally {
      setSaving(false)
    }
  }

  const handleCloseImport = () => {
    setShowImport(false)
    setImportText('')
    setParsedQuestions([])
    setParseError('')
    setUnparsedLines([])
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
        explanation: '',
        keterangan: '',
        isNew: true,
      },
    ])
    Swal.fire({ icon: 'success', title: 'Soal baru ditambahkan', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true })
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

  const saveQuestion = async (qIndex) => {
    const q = questions[qIndex]
    if (!q.body.trim()) {
      Swal.fire({ icon: 'error', title: 'Validasi gagal', text: 'Isi pertanyaan wajib diisi', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
      return
    }
    setSaving(true)
    try {
      const payload = {
        question: {
          body: q.body,
          correct_choice_index: q.correctChoiceIndex ?? 0,
          question_type: 'choice',
          explanation: q.explanation || '',
          keterangan: q.keterangan || '',
        },
        choices: [],
      }

      const validChoices = (q.choices || []).filter((c) => typeof c === 'string' && c.trim())
      if (validChoices.length < 2) {
        setSaving(false)
        Swal.fire({ icon: 'error', title: 'Validasi gagal', text: 'Minimal 2 pilihan jawaban', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
        return
      }
      payload.choices = validChoices.map((text) => ({ text }))

      if (q.id) {
        await examApi.updateQuestion(id, q.id, payload)
      } else {
        const res = await examApi.addQuestion(id, payload)
        const updated = [...questions]
        updated[qIndex].id = res.question_id
        setQuestions(updated)
      }
      setSaving(false)
      Swal.fire({ icon: 'success', title: 'Soal berhasil disimpan', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true })
    } catch (err) {
      setSaving(false)
      Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: err.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true })
    }
  }

  const removeQuestion = async (qIndex) => {
    const q = questions[qIndex]
    const result = await Swal.fire({
      title: 'Hapus soal?',
      text: 'Soal yang dihapus tidak dapat dikembalikan.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#94a3b8',
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
    })
    if (!result.isConfirmed) return
    try {
      if (q.id) {
        await examApi.deleteQuestion(id, q.id)
      }
      setQuestions(questions.filter((_, i) => i !== qIndex))
      Swal.fire({ icon: 'success', title: 'Soal dihapus', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true })
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Gagal menghapus', text: err.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true })
    }
  }

  if (loading) {
    return (
      <div className="min-h-screen bg-transparent flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  if (!exam) {
    return (
      <div className="min-h-screen bg-transparent flex items-center justify-center">
        <div className="text-center">
          <p className="text-slate-500 mb-4">Ujian tidak ditemukan</p>
          <Link to="/dashboard" className="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
            ← Kembali ke Dashboard
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-transparent">
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
          {questions.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE).map((q, localIndex) => {
            const qIndex = (currentPage - 1) * ITEMS_PER_PAGE + localIndex
            return (
            <div
              key={q.id || qIndex}
              className="bg-white/80 backdrop-blur-lg rounded-2xl border border-white p-6 shadow-sm hover:shadow-md transition-all"
            >
              <div className="flex items-center justify-between mb-4">
                <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg uppercase tracking-wider">
                  Soal {qIndex + 1}
                </span>
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => saveQuestion(qIndex)}
                    disabled={saving}
                    className="text-xs bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 text-white font-medium px-4 py-2 rounded-xl shadow-md transition-all"
                  >
                    {saving ? 'Menyimpan...' : 'Simpan'}
                  </button>
                  <button
                    onClick={() => removeQuestion(qIndex)}
                    className="text-slate-300 hover:text-red-500 transition text-sm"
                  >
                    Hapus
                  </button>
                </div>
              </div>

              <div className="mb-4">
                <textarea
                  value={q.body}
                  onChange={(e) => updateQuestion(qIndex, 'body', e.target.value)}
                  className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y min-h-[80px] transition-all"
                  placeholder="Tulis pertanyaan di sini..."
                />
              </div>

              <div className="mt-3 space-y-3">
                {q.choices.map((choice, cIndex) => (
                  <div key={cIndex} className="flex items-center gap-3">
                    <button
                      onClick={() => updateQuestion(qIndex, 'correctChoiceIndex', cIndex)}
                      className={`w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold transition-all shrink-0 ${
                        q.correctChoiceIndex === cIndex
                          ? 'border-emerald-500 bg-emerald-500 text-white shadow-md shadow-emerald-200'
                          : 'border-slate-200 text-slate-400 hover:border-indigo-300 hover:text-indigo-500 bg-white'
                      }`}
                      title="Jawaban benar"
                    >
                      {LABELS[cIndex]}
                    </button>
                    <input
                      type="text"
                      value={choice}
                      onChange={(e) => updateChoice(qIndex, cIndex, e.target.value)}
                      className="flex-1 bg-white/50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                      placeholder={`Pilihan ${LABELS[cIndex]}`}
                    />
                  </div>
                ))}
              </div>
              <p className="text-xs text-slate-400 mt-3 pl-11">
                Klik huruf untuk tandai jawaban yang benar
              </p>

              <textarea
                value={q.explanation || ''}
                onChange={(e) => updateQuestion(qIndex, 'explanation', e.target.value)}
                className="w-full mt-4 bg-white/50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y min-h-[60px] transition-all"
                placeholder="Pembahasan (opsional)..."
              />

              <textarea
                value={q.keterangan || ''}
                onChange={(e) => updateQuestion(qIndex, 'keterangan', e.target.value)}
                className="w-full mt-3 bg-white/50 border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y min-h-[60px] transition-all"
                placeholder="Keterangan / informasi tambahan..."
              />
            </div>
            )
          })}
        </div>

        {questions.length > ITEMS_PER_PAGE && (
          <div className="flex items-center justify-between border-t border-slate-200 pt-4 mb-6">
            <p className="text-sm text-slate-500">
              Menampilkan {(currentPage - 1) * ITEMS_PER_PAGE + 1}–{Math.min(currentPage * ITEMS_PER_PAGE, questions.length)} dari {questions.length} soal
            </p>
            <div className="flex items-center gap-1">
              <button
                onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
              >
                ←
              </button>
              {Array.from({ length: Math.ceil(questions.length / ITEMS_PER_PAGE) }, (_, i) => i + 1).map(page => (
                <button
                  key={page}
                  onClick={() => setCurrentPage(page)}
                  className={`w-8 h-8 text-sm rounded-lg transition ${
                    page === currentPage
                      ? 'bg-blue-600 text-white'
                      : 'border border-slate-300 hover:bg-slate-50 text-slate-600'
                  }`}
                >
                  {page}
                </button>
              ))}
              <button
                onClick={() => setCurrentPage(p => Math.min(Math.ceil(questions.length / ITEMS_PER_PAGE), p + 1))}
                disabled={currentPage === Math.ceil(questions.length / ITEMS_PER_PAGE)}
                className="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
              >
                →
              </button>
            </div>
          </div>
        )}

        <div className="flex flex-col sm:flex-row items-center gap-4 mb-8">
          <button
            onClick={addQuestion}
            className="w-full sm:flex-1 border-2 border-dashed border-indigo-200 hover:border-indigo-400 text-indigo-500 hover:text-indigo-600 bg-indigo-50/50 hover:bg-indigo-50 text-sm font-semibold px-4 py-3 rounded-2xl transition-all"
          >
            + Tambah Soal
          </button>
          <button
            onClick={() => setShowImport(true)}
            className="w-full sm:flex-1 border-2 border-dashed border-emerald-200 hover:border-emerald-400 text-emerald-500 hover:text-emerald-600 bg-emerald-50/50 hover:bg-emerald-50 text-sm font-semibold px-4 py-3 rounded-2xl transition-all"
          >
            + Import Soal
          </button>
        </div>

        <div className="flex justify-end mt-6">
          <Link
            to="/dashboard"
            className="text-sm text-slate-500 hover:text-slate-700 px-4 py-2"
          >
            ← Kembali ke Dashboard
          </Link>
        </div>
      </div>

      {showImport && (
        <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="sticky top-0 bg-white/90 backdrop-blur-xl border-b border-slate-200/50 px-6 py-5 rounded-t-3xl flex items-center justify-between">
              <h2 className="text-xl font-bold text-slate-800">Import Soal</h2>
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
                <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3 whitespace-pre-wrap">
                  {parseError}
                </div>
              )}

              {unparsedLines.length > 0 && parsedQuestions.length === 0 && (
                <div className="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-lg px-4 py-3">
                  <p className="font-semibold mb-1">Baris tidak dikenali:</p>
                  <ul className="list-disc list-inside text-xs space-y-0.5">
                    {unparsedLines.slice(0, 5).map((line, i) => (
                      <li key={i} className="font-mono break-all">{line}</li>
                    ))}
                    {unparsedLines.length > 5 && <li className="text-amber-500 italic">...dan {unparsedLines.length - 5} baris lainnya</li>}
                  </ul>
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

            <div className="sticky bottom-0 bg-white/90 backdrop-blur-xl border-t border-slate-200/50 px-6 py-4 rounded-b-3xl flex justify-end gap-3">
              <button
                onClick={handleCloseImport}
                className="text-sm font-medium text-slate-500 hover:text-slate-700 px-4 py-2.5"
              >
                Batal
              </button>
              <button
                onClick={handleParse}
                className="text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 px-5 py-2.5 rounded-xl transition-all"
              >
                Parse Teks
              </button>
              <button
                onClick={handleImport}
                disabled={parsedQuestions.length === 0}
                className="text-sm bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium px-5 py-2.5 rounded-xl shadow-md transition-all"
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
