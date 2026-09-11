import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { AuthProvider, useAuth } from './context/AuthContext'
import Navbar from './components/Navbar'
import VerifyEmail from './pages/VerifyEmail'
import Dashboard from './pages/Dashboard'
import ExamBuilder from './pages/ExamBuilder'
import AvailableExams from './pages/AvailableExams'
import TakeExam from './pages/TakeExam'
import ExamResult from './pages/ExamResult'
import Profile from './pages/Profile'
import Admin from './pages/Admin'
import AdminLogin from './pages/AdminLogin'
import { ProtectedRoute } from './context/AuthContext'

function AppRoutes() {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 flex items-center justify-center">
        <div className="text-slate-400">Memuat...</div>
      </div>
    )
  }

  const isTakeExam = location.pathname.startsWith('/take/')

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-cyan-50 text-slate-800">
      {!isTakeExam && <Navbar />}
      <Routes>
        <Route path="/" element={<Navigate to="/exams" replace />} />
        <Route path="/verify-email" element={<VerifyEmail />} />
        <Route
          path="/dashboard"
          element={
            <ProtectedRoute>
              <Dashboard />
            </ProtectedRoute>
          }
        />
        <Route
          path="/exam/:id/build"
          element={
            <ProtectedRoute>
              <ExamBuilder />
            </ProtectedRoute>
          }
        />
        <Route path="/exams" element={<AvailableExams />} />
        <Route path="/take/:id" element={<TakeExam />} />
        <Route path="/result/:attemptId" element={<ExamResult />} />
        <Route
          path="/profile"
          element={
            <ProtectedRoute>
              <Profile />
            </ProtectedRoute>
          }
        />
        <Route path="/admin" element={user?.role === 'admin' ? <Navigate to="/dashboard" replace /> : <Navigate to="/admin-login" replace />} />
        <Route path="/admin-login" element={<AdminLogin />} />
      </Routes>
    </div>
  )
}

export default function App() {
  return (
    <AuthProvider>
      <AppRoutes />
    </AuthProvider>
  )
}
