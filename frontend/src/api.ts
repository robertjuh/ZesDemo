// Tiny typed client for the Daily Coach API. Uses fetch only — no state library.

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000'

export interface CheckIn {
  id: number
  energyLevel: number
  focusGoal: string
  distractionRisk: string
  notes: string | null
  createdAt: string
}

export interface Recommendation {
  id: number
  checkInId: number
  priority: string
  riskLevel: string
  nextAction: string
  reasoning: string
  createdAt: string
}

export interface CreateCheckInInput {
  energyLevel: number
  focusGoal: string
  distractionRisk: string
  notes: string
}

interface ApiErrorBody {
  error?: string
  violations?: { field: string; message: string }[]
}

/** Turn a non-2xx response into a readable Error. */
async function toError(response: Response): Promise<Error> {
  let body: ApiErrorBody = {}
  try {
    body = (await response.json()) as ApiErrorBody
  } catch {
    // body was not JSON; fall back to the status text
  }

  if (body.violations?.length) {
    const details = body.violations.map((v) => `${v.field}: ${v.message}`).join('; ')
    return new Error(details)
  }

  return new Error(body.error ?? `Request failed (HTTP ${response.status})`)
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: { 'Content-Type': 'application/json' },
    ...init,
  })

  if (!response.ok) {
    throw await toError(response)
  }

  return (await response.json()) as T
}

export function createCheckIn(input: CreateCheckInInput): Promise<CheckIn> {
  return request<CheckIn>('/api/checkins', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export function getRecentCheckIns(): Promise<CheckIn[]> {
  return request<CheckIn[]>('/api/checkins')
}

/** Returns null when there is no check-in for today (clean 404 from the API). */
export async function getTodayCheckIn(): Promise<CheckIn | null> {
  const response = await fetch(`${API_BASE_URL}/api/checkins/today`)
  if (response.status === 404) {
    return null
  }
  if (!response.ok) {
    throw await toError(response)
  }
  return (await response.json()) as CheckIn
}

export function generateRecommendation(checkInId: number): Promise<Recommendation> {
  return request<Recommendation>('/api/recommendations/generate', {
    method: 'POST',
    body: JSON.stringify({ checkInId }),
  })
}
