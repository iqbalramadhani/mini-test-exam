import '@testing-library/jest-dom/vitest'
import { vi } from 'vitest'

vi.stubGlobal('fetch', vi.fn())
vi.stubGlobal('sessionStorage', {
  getItem: vi.fn(() => null),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
  length: 0,
  key: vi.fn(),
} as unknown as Storage)
