import { useState, useEffect } from 'react'
import { adminApi } from '../api'

export default function Admin() {
  const [activeTab, setActiveTab] = useState('dashboard')
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const fetchLogs = async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await adminApi.getLogs()
      setLogs(data.logs || [])
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (activeTab === 'logs') {
      fetchLogs()
    }
  }, [activeTab])

  return (
    <div className="max-w-5xl mx-auto p-4 sm:p-8">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight">Panel Admin</h1>
        <div className="flex bg-slate-100 p-1 rounded-xl w-fit">
          <button
            onClick={() => setActiveTab('dashboard')}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${activeTab === 'dashboard' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
          >
            Dashboard
          </button>
          <button
            onClick={() => setActiveTab('logs')}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${activeTab === 'logs' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}
          >
            Server Logs
          </button>
        </div>
      </div>

      {activeTab === 'dashboard' && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
          <p className="text-slate-600">Selamat datang, admin.</p>
        </div>
      )}

      {activeTab === 'logs' && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-[70vh]">
          <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h2 className="font-bold text-slate-800">Log Aplikasi (500 baris terakhir)</h2>
            <button
              onClick={fetchLogs}
              disabled={loading}
              className="text-sm bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 py-2 rounded-lg font-medium transition-colors disabled:opacity-50"
            >
              {loading ? 'Memuat...' : 'Refresh'}
            </button>
          </div>
          <div className="p-0 flex-1 overflow-auto bg-slate-900">
            {error ? (
              <div className="p-6 text-red-400 font-mono text-sm">{error}</div>
            ) : (
              <pre className="p-6 font-mono text-xs text-slate-300 whitespace-pre-wrap break-all">
                {logs.length > 0 ? logs.join('\n') : 'Tidak ada log.'}
              </pre>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
