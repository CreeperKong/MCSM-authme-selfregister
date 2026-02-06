const rawBase = import.meta.env.VITE_API_BASE_URL || '/backend/api'
const API_BASE = rawBase.endsWith('/') ? rawBase.slice(0, -1) : rawBase

async function request(path, options = {}) {
  const { method = 'GET', headers = {}, body, signal } = options
  const url = `${API_BASE}${path.startsWith('/') ? path : `/${path}`}`
  const init = {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...headers,
    },
    credentials: 'same-origin',
    signal,
  }

  if (body !== undefined && body !== null) {
    init.body = typeof body === 'string' ? body : JSON.stringify(body)
  } else if (method === 'POST' || method === 'PUT' || method === 'PATCH') {
    init.body = JSON.stringify({})
  }

  const response = await fetch(url, init)
  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json')
    ? await response.json()
    : await response.text()

  if (!response.ok || (payload && payload.status === 'error')) {
    const message = typeof payload === 'string' ? payload : payload?.message
    const details = typeof payload === 'string' ? null : payload?.details
    const error = new Error(message || `请求失败 (${response.status})`)
    error.status = response.status
    error.details = details
    throw error
  }

  return payload?.data ?? payload
}

function adminHeaders(token) {
  return {
    'X-Admin-Token': token,
  }
}

export const apiClient = {
  request,
  fetchConfig: () => request('/config.php'),
  fetchCaptchaChallenge: () => request('/captcha.php'),
  submitRegistration: (payload) => request('/register.php', { method: 'POST', body: payload }),
  fetchRequests: (token, status = 'pending') =>
    request(`/requests.php?status=${encodeURIComponent(status)}`, {
      headers: adminHeaders(token),
    }),
  processRequest: (token, payload) =>
    request('/requests.php', {
      method: 'POST',
      headers: adminHeaders(token),
      body: payload,
    }),
  fetchDaemons: (token) =>
    request('/daemons.php', {
      headers: adminHeaders(token),
    }),
  fetchInstances: (token, daemonId, page = 1, pageSize = 20) =>
    request(`/instances.php?daemonId=${encodeURIComponent(daemonId)}&page=${page}&pageSize=${pageSize}`, {
      headers: adminHeaders(token),
    }),
  saveSelection: (token, daemonId, instanceId) =>
    request('/save_selection.php', {
      method: 'POST',
      headers: adminHeaders(token),
      body: { daemonId, instanceId },
    }),
  updateCommandTemplate: (token, commandTemplate) =>
    request('/update_config.php', {
      method: 'POST',
      headers: adminHeaders(token),
      body: { commandTemplate },
    }),
}

export default apiClient
