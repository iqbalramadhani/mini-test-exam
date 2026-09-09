import { describe, it, expect, vi, beforeEach } from 'vitest'

beforeEach(() => {
  vi.stubGlobal('fetch', vi.fn())
  vi.stubGlobal('sessionStorage', {
    getItem: vi.fn(() => 'test-token'),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn(),
  })
})

describe('authApi', () => {
  it('register sends POST to /auth/register', async () => {
    const { authApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ user: { id: 1, username: 'test' } }),
    })

    const result = await authApi.register({ username: 'test', email: 'test@mail.com', password: 'password' })
    expect(fetch).toHaveBeenCalledWith('/api/auth/register', expect.objectContaining({ method: 'POST' }))
    expect(result.user.username).toBe('test')
  })

  it('login sends POST to /auth/login', async () => {
    const { authApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ user: { id: 1 } }),
    })

    await authApi.login({ identifier: 'test', password: 'pass' })
    expect(fetch).toHaveBeenCalledWith('/api/auth/login', expect.objectContaining({ method: 'POST' }))
  })

  it('me sends GET to /auth/me', async () => {
    const { authApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ user: { id: 1 } }),
    })

    await authApi.me()
    expect(fetch).toHaveBeenCalledWith('/api/auth/me', expect.any(Object))
  })
})

describe('examApi', () => {
  it('list sends GET to /exams', async () => {
    const { examApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ([]),
    })

    await examApi.list()
    expect(fetch).toHaveBeenCalledWith('/api/exams', expect.any(Object))
  })

  it('create sends POST to /exams', async () => {
    const { examApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ id: 1 }),
    })

    await examApi.create({ title: 'Ujian', description: 'Test' })
    expect(fetch).toHaveBeenCalledWith('/api/exams', expect.objectContaining({ method: 'POST' }))
  })

  it('get sends GET to /exams/:id', async () => {
    const { examApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ id: 1 }),
    })

    await examApi.get(1)
    expect(fetch).toHaveBeenCalledWith('/api/exams/1', expect.any(Object))
  })
})

describe('attemptApi', () => {
  it('start sends POST to /attempts/start/:examId', async () => {
    const { attemptApi } = await import('../api')
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ id: 1 }),
    })

    await attemptApi.start(1)
    expect(fetch).toHaveBeenCalledWith('/api/attempts/start/1', expect.objectContaining({ method: 'POST' }))
  })
})
