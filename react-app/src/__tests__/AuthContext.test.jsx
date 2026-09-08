import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { AuthProvider, useAuth } from '../context/AuthContext'

vi.mock('../api', () => ({
  authApi: {
    me: vi.fn().mockResolvedValue({ user: null }),
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    updateProfile: vi.fn(),
    changePassword: vi.fn(),
  },
}))

import { authApi } from '../api'

function wrapper({ children }) {
  return <AuthProvider>{children}</AuthProvider>
}

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authApi.me.mockResolvedValue({ user: null })
  })

  it('login calls authApi.login and sets user', async () => {
    authApi.login.mockResolvedValue({ user: { id: 1, username: 'test' } })

    const { result } = renderHook(() => useAuth(), { wrapper })

    await act(async () => {
      await result.current.login('test', 'pass')
    })

    expect(result.current.user).toEqual({ id: 1, username: 'test' })
  })

  it('logout calls authApi.logout and clears user', async () => {
    authApi.login.mockResolvedValue({ user: { id: 1, username: 'test' } })
    authApi.logout.mockResolvedValue(undefined)

    const { result } = renderHook(() => useAuth(), { wrapper })

    await act(async () => {
      await result.current.login('test', 'pass')
    })

    await act(async () => {
      await result.current.logout()
    })

    expect(result.current.user).toBeNull()
    expect(result.current.isAuthenticated).toBe(false)
  })
})
