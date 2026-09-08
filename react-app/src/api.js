const API = '/api'

async function request(endpoint, options = {}) {
  const token = sessionStorage.getItem('token')
  const res = await fetch(API + endpoint, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    credentials: 'include',
  })
  const data = await res.json()
  if (!res.ok) {
    throw new Error(data.error || `HTTP ${res.status}`)
  }
  return data
}

export const authApi = {
  register: (data) => request('/auth/register', { method: 'POST', body: JSON.stringify(data) }),
  login: (data) => request('/auth/login', { method: 'POST', body: JSON.stringify(data) }),
  me: () => request('/auth/me'),
  logout: () => request('/auth/logout', { method: 'POST' }),
}

export const examApi = {
  list: () => request('/exams'),
  get: (id) => request(`/exams/${id}`),
  create: (data) => request('/exams', { method: 'POST', body: JSON.stringify(data) }),
  update: (id, data) => request(`/exams/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  del: (id) => request(`/exams/${id}`, { method: 'DELETE' }),
  listQuestions: (examId) => request(`/exams/${examId}/questions`),
  addQuestion: (examId, data) => request(`/exams/${examId}/questions`, { method: 'POST', body: JSON.stringify(data) }),
  updateQuestion: (examId, qId, data) => request(`/exams/${examId}/questions/${qId}`, { method: 'PUT', body: JSON.stringify(data) }),
  deleteQuestion: (examId, qId) => request(`/exams/${examId}/questions/${qId}`, { method: 'DELETE' }),
}
