import { useCallback, useEffect, useMemo, useState } from 'react'
import RequestCard from '../components/RequestCard.jsx'
import { apiClient } from '../api/client.js'

const TABS = [
  { value: 'pending', label: '待审核' },
  { value: 'approved', label: '已批准' },
  { value: 'rejected', label: '已拒绝' },
]

const TOKEN_STORAGE_KEY = 'mcsm-admin-token'

export default function AdminPage() {
  const [adminToken, setAdminToken] = useState(() => localStorage.getItem(TOKEN_STORAGE_KEY) || '')
  const [tokenInput, setTokenInput] = useState(() => localStorage.getItem(TOKEN_STORAGE_KEY) || '')
  const [config, setConfig] = useState(null)
  const [requests, setRequests] = useState([])
  const [statusFilter, setStatusFilter] = useState('pending')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(null)

  const loggedIn = Boolean(adminToken)

  const loadConfig = useCallback(async () => {
    try {
      const data = await apiClient.fetchConfig()
      setConfig(data)
    } catch (err) {
      setError(err.message)
    }
  }, [])

  const fetchRequests = useCallback(async () => {
    if (!adminToken) return
    setLoading(true)
    setError(null)
    try {
      const data = await apiClient.fetchRequests(adminToken, statusFilter)
      setRequests(data.items || [])
    } catch (err) {
      if (err.status === 401) {
        setError('管理员令牌无效或已过期。')
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        setAdminToken('')
      } else {
        setError(err.message)
      }
    } finally {
      setLoading(false)
    }
  }, [adminToken, statusFilter])

  useEffect(() => {
    loadConfig()
  }, [loadConfig])

  useEffect(() => {
    fetchRequests()
  }, [fetchRequests])

  const handleLogin = (event) => {
    event.preventDefault()
    if (!tokenInput.trim()) return
    localStorage.setItem(TOKEN_STORAGE_KEY, tokenInput.trim())
    setAdminToken(tokenInput.trim())
  }

  const handleLogout = () => {
    localStorage.removeItem(TOKEN_STORAGE_KEY)
    setAdminToken('')
    setTokenInput('')
    setRequests([])
  }

  const mutateRequest = async (action, payload) => {
    if (!adminToken) return
    setSubmitting({ id: payload.requestId, action })
    setError(null)
    try {
      await apiClient.processRequest(adminToken, {
        action,
        request_id: payload.requestId,
        daemon_id: payload.daemonId,
        instance_id: payload.instanceId,
        notes: payload.notes,
        reason: payload.reason,
      })
      await fetchRequests()
    } catch (err) {
      setError(err.message)
    } finally {
      setSubmitting(null)
    }
  }

  const defaults = useMemo(
    () => ({
      daemonId: config?.mcsm?.defaultDaemonId || '',
      instanceId: config?.mcsm?.defaultInstanceId || '',
    }),
    [config],
  )

  if (!loggedIn) {
    return (
      <div className="page-shell">
        <div className="panel login-card">
          <h1>管理员登录</h1>
          <p className="panel-subtitle">使用 MCSManager 账号生成的 API Key 登录审核面板。</p>
          <form className="form-grid" onSubmit={handleLogin}>
            <div className="field" style={{ gridColumn: '1 / -1' }}>
              <label htmlFor="admin-token">API Key</label>
              <textarea
                id="admin-token"
                required
                value={tokenInput}
                onChange={(event) => setTokenInput(event.target.value)}
                placeholder="粘贴管理员 API Key"
                style={{ minHeight: '160px' }}
              />
            </div>
            <div className="btn-row">
              <button type="submit" className="btn">
                进入控制台
              </button>
            </div>
          </form>
          {error && <div className="alert error">{error}</div>}
        </div>
      </div>
    )
  }

  return (
    <div className="page-shell">
      <div className="panel" style={{ width: '100%' }}>
        <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '1rem' }}>
          <div>
            <h1>注册审核中心</h1>
            <p className="panel-subtitle">当前令牌：•••• •••• {adminToken.slice(-6)}</p>
          </div>
          <button className="btn secondary" type="button" onClick={handleLogout}>
            退出登录
          </button>
        </header>

        <div className="tab-list">
          {TABS.map((tab) => (
            <button
              key={tab.value}
              className={tab.value === statusFilter ? 'active' : ''}
              type="button"
              onClick={() => setStatusFilter(tab.value)}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div className="admin-grid">
          <div className="sidebar-card">
            <h3 style={{ marginTop: 0 }}>面板状态</h3>
            <p style={{ color: 'rgba(148,163,184,0.85)' }}>
              默认节点：<strong>{defaults.daemonId || '未配置'}</strong>
              <br />默认实例：<strong>{defaults.instanceId || '未配置'}</strong>
              <br />命令模板：
              <span className="inline-code">{config?.mcsm?.commandTemplate || 'authme register {username} {password} {password}'}</span>
            </p>
            <button className="btn secondary" type="button" onClick={fetchRequests} disabled={loading}>
              {loading ? '同步中...' : '刷新列表'}
            </button>
          </div>

          <div>
            {error && <div className="alert error">{error}</div>}
            {!error && loading && <div className="info-box">正在加载请求...</div>}
            {requests.length === 0 && !loading && (
              <div className="info-box">暂无 {TABS.find((tab) => tab.value === statusFilter)?.label} 请求</div>
            )}

            <div className="request-list">
              {requests.map((request) => (
                <RequestCard
                  key={request.id}
                  request={request}
                  defaults={defaults}
                  submitting={submitting}
                  onApprove={(payload) => mutateRequest('approve', payload)}
                  onReject={(payload) => mutateRequest('reject', payload)}
                />
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
