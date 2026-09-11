import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Profile() {
  const { user, updateProfile, changePassword, logout } = useAuth()
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState('name')

  const [name, setName] = useState(user?.name || '')
  const [nameError, setNameError] = useState('')
  const [nameSuccess, setNameSuccess] = useState(false)
  const [nameLoading, setNameLoading] = useState(false)

  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [passwordError, setPasswordError] = useState('')
  const [passwordSuccess, setPasswordSuccess] = useState(false)
  const [passwordLoading, setPasswordLoading] = useState(false)

  const handleNameSubmit = async (e) => {
    e.preventDefault()
    setNameError('')
    setNameSuccess(false)

    if (name === (user?.name || user?.username)) {
      setNameError('Nama tidak berubah')
      return
    }

    setNameLoading(true)
    try {
      await updateProfile(name)
      setNameSuccess(true)
    } catch (err) {
      setNameError(err.message)
    } finally {
      setNameLoading(false)
    }
  }

  const handlePasswordSubmit = async (e) => {
    e.preventDefault()
    setPasswordError('')
    setPasswordSuccess(false)

    if (newPassword !== confirmPassword) {
      setPasswordError('Konfirmasi password tidak cocok')
      return
    }

    setPasswordLoading(true)
    try {
      await changePassword(currentPassword, newPassword)
      setPasswordSuccess(true)
      setCurrentPassword('')
      setNewPassword('')
      setConfirmPassword('')
    } catch (err) {
      setPasswordError(err.message)
    } finally {
      setPasswordLoading(false)
    }
  }

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <div className="min-h-screen bg-transparent py-8 px-4">
      <div className="max-w-2xl mx-auto">
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={() => navigate(-1)}
            className="p-2.5 bg-white/50 hover:bg-white rounded-xl shadow-sm border border-slate-200/50 text-slate-600 hover:text-indigo-600 transition-all"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight">Profil</h1>
        </div>

        <div className="bg-white/80 backdrop-blur-xl rounded-3xl shadow-xl border border-white overflow-hidden">
          <div className="flex border-b border-slate-200/60 bg-white/50">
            <button
              onClick={() => setActiveTab('name')}
              className={`flex-1 py-4 text-sm font-semibold transition-all ${
                activeTab === 'name'
                  ? 'text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50/50'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              }`}
            >
              Ubah Nama
            </button>
            <button
              onClick={() => setActiveTab('password')}
              className={`flex-1 py-4 text-sm font-semibold transition-all ${
                activeTab === 'password'
                  ? 'text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50/50'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
              }`}
            >
              Ubah Password
            </button>
          </div>

          <div className="p-6">
            {activeTab === 'name' && (
              <div>
                <div className="mb-6">
                  <p className="text-sm text-slate-500">Username (untuk login)</p>
                  <p className="text-sm text-slate-800 font-medium">{user?.username}</p>
                </div>
                <div className="mb-6">
                  <p className="text-sm text-slate-500">Email</p>
                  <p className="text-sm text-slate-800 font-medium">{user?.email}</p>
                </div>

                <form onSubmit={handleNameSubmit} className="space-y-5">
                  <div>
                    <label className="block text-sm font-semibold text-slate-700 mb-1.5">Nama Panggilan</label>
                    <input
                      type="text"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                      placeholder="Masukkan nama panggilan"
                      minLength={1}
                      maxLength={100}
                      required
                    />
                  </div>

                  {nameError && (
                    <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                      {nameError}
                    </div>
                  )}
                  {nameSuccess && (
                    <div className="bg-green-50 border border-green-200 text-green-600 text-sm rounded-lg px-4 py-3">
                      Nama berhasil diubah!
                    </div>
                  )}

                  <button
                    type="submit"
                    disabled={nameLoading}
                    className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 text-white font-medium px-6 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all"
                  >
                    {nameLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
                  </button>
                </form>
              </div>
            )}

            {activeTab === 'password' && (
              <form onSubmit={handlePasswordSubmit} className="space-y-5">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-1.5">Password Saat Ini</label>
                  <input
                    type="password"
                    value={currentPassword}
                    onChange={(e) => setCurrentPassword(e.target.value)}
                    className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    placeholder="••••••••"
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-1.5">Password Baru</label>
                  <input
                    type="password"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    placeholder="Minimal 6 karakter"
                    minLength={6}
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-1.5">Konfirmasi Password Baru</label>
                  <input
                    type="password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    className="w-full bg-white/50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    placeholder="••••••••"
                    minLength={6}
                    required
                  />
                </div>

                {passwordError && (
                  <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg px-4 py-3">
                    {passwordError}
                  </div>
                )}
                {passwordSuccess && (
                  <div className="bg-green-50 border border-green-200 text-green-600 text-sm rounded-lg px-4 py-3">
                    Password berhasil diubah!
                  </div>
                )}

                <button
                  type="submit"
                  disabled={passwordLoading}
                  className="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-50 text-white font-medium px-6 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all"
                >
                  {passwordLoading ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </form>
            )}
          </div>
        </div>

        <div className="mt-8 text-center pb-8">
          <button
            onClick={handleLogout}
            className="text-sm font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-6 py-3 rounded-full transition-all inline-flex items-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Keluar dari akun
          </button>
        </div>
      </div>
    </div>
  )
}
