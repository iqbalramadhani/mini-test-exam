import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import Navbar from '../components/Navbar'
import { AuthProvider, AuthContext } from '../context/AuthContext'

vi.mock('../api', () => ({
  authApi: {
    me: () => Promise.resolve({ user: null }),
    logout: () => Promise.resolve(),
  },
}))

function renderWithAuth(user = null) {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={{ user, loading: false, login: vi.fn(), register: vi.fn(), logout: vi.fn(), isAuthenticated: !!user }}>
        <Navbar />
      </AuthContext.Provider>
    </MemoryRouter>
  )
}

describe('Navbar', () => {
  it('renders logo link', () => {
    renderWithAuth(null)
    expect(screen.getByText('Ujian')).toBeInTheDocument()
  })

  it('shows login/register links when not authenticated', () => {
    renderWithAuth(null)
    expect(screen.getByText('Masuk')).toBeInTheDocument()
    expect(screen.getByText('Daftar')).toBeInTheDocument()
  })

  it('shows profile links when authenticated', () => {
    renderWithAuth({ name: 'Test User', username: 'testuser' })
    expect(screen.getByText('Test User')).toBeInTheDocument()
    expect(screen.getByText('Dashboard')).toBeInTheDocument()
    expect(screen.getByText('Keluar')).toBeInTheDocument()
  })
})
