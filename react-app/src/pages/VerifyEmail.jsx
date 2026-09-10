import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { authApi } from '../api'

export default function VerifyEmail() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token')
  
  const [status, setStatus] = useState('loading') // loading, success, error
  const [message, setMessage] = useState('Sedang memverifikasi email Anda...')

  useEffect(() => {
    if (!token) {
      setStatus('error')
      setMessage('Token verifikasi tidak ditemukan.')
      return
    }

    authApi.verifyEmail(token)
      .then((res) => {
        setStatus('success')
        setMessage(res.message || 'Email berhasil diverifikasi!')
      })
      .catch((err) => {
        setStatus('error')
        setMessage(err.message || 'Gagal memverifikasi email.')
      })
  }, [token])

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-cyan-50 px-4">
      <div className="bg-white/80 backdrop-blur-xl rounded-2xl shadow-2xl shadow-indigo-200/50 border border-white p-8 w-full max-w-md transition-all text-center">
        
        {status === 'loading' && (
          <div className="mb-4">
            <div className="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
          </div>
        )}
        
        {status === 'success' && (
          <div className="w-16 h-16 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
          </div>
        )}

        {status === 'error' && (
          <div className="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </div>
        )}

        <h1 className="text-2xl font-bold text-slate-800 mb-2">Verifikasi Email</h1>
        <p className={`text-sm mb-6 ${status === 'error' ? 'text-red-500' : 'text-slate-500'}`}>
          {message}
        </p>

        {status !== 'loading' && (
          <Link to="/login" className="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-xl transition-all shadow-md">
            Lanjut ke Login
          </Link>
        )}
      </div>
    </div>
  )
}
