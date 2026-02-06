import { useCallback, useEffect, useMemo, useState, useRef } from 'react'
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

  // New state for daemons and instances
  const [daemons, setDaemons] = useState([])
  const [instances, setInstances] = useState([])
  const [selectedDaemonId, setSelectedDaemonId] = useState('')
  const [selectedInstanceId, setSelectedInstanceId] = useState('')
  const [loadingDaemons, setLoadingDaemons] = useState(false)
  const [loadingInstances, setLoadingInstances] = useState(false)
  const [showDaemonDialog, setShowDaemonDialog] = useState(false)
  const [showInstanceDialog, setShowInstanceDialog] = useState(false)
  const [instancePage, setInstancePage] = useState(1)
  const [instancesMaxPage, setInstancesMaxPage] = useState(1)
  const [showCommandTemplateDialog, setShowCommandTemplateDialog] = useState(false)
  const [commandTemplateInput, setCommandTemplateInput] = useState('')
  const [commandTemplateSaveStatus, setCommandTemplateSaveStatus] = useState('idle') // 'idle', 'saving', 'saved', 'error'
  const commandTemplateSaveTimerRef = useRef(null)

  const loggedIn = Boolean(adminToken)

  const loadConfig = useCallback(async () => {
    try {
      const data = await apiClient.fetchConfig()
      setConfig(data)
    } catch (err) {
      setError(err.message)
    }
  }, [])

  const loadDaemons = useCallback(async () => {
    if (!adminToken) return
    setLoadingDaemons(true)
    try {
      const data = await apiClient.fetchDaemons(adminToken)
      console.log('Daemons loaded:', data)
      setDaemons(data.daemons || [])
      // Set daemon from config default or first daemon
      if (data.daemons && data.daemons.length > 0) {
        const defaultDaemonId = config?.mcsm?.defaultDaemonId
        if (defaultDaemonId && data.daemons.find(d => d.id === defaultDaemonId)) {
          setSelectedDaemonId(defaultDaemonId)
        } else {
          setSelectedDaemonId(data.daemons[0].id)
        }
      }
    } catch (err) {
      console.error('Failed to load daemons:', err)
      setError(`获取节点列表失败: ${err.message}`)
    } finally {
      setLoadingDaemons(false)
    }
  }, [adminToken, config?.mcsm?.defaultDaemonId])

  const loadInstances = useCallback(async (page = 1) => {
    if (!adminToken || !selectedDaemonId) return
    setLoadingInstances(true)
    try {
      const data = await apiClient.fetchInstances(adminToken, selectedDaemonId, page, 20)
      console.log('Instances loaded:', data)
      setInstances(data.instances || [])
      if (data.pagination) {
        setInstancePage(data.pagination.page)
        setInstancesMaxPage(data.pagination.maxPage)
      }
      // Set instance from config default or first instance on first page
      if (page === 1 && data.instances && data.instances.length > 0) {
        const defaultInstanceId = config?.mcsm?.defaultInstanceId
        if (defaultInstanceId && data.instances.find(i => i.id === defaultInstanceId)) {
          setSelectedInstanceId(defaultInstanceId)
        } else {
          setSelectedInstanceId(data.instances[0].id)
        }
      }
    } catch (err) {
      console.error('Failed to load instances:', err)
      setError(`获取实例列表失败: ${err.message}`)
      setInstances([])
      setInstancePage(1)
      setInstancesMaxPage(1)
    } finally {
      setLoadingInstances(false)
    }
  }, [adminToken, selectedDaemonId, config?.mcsm?.defaultInstanceId])

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

  // Cleanup timer on unmount
  useEffect(() => {
    return () => {
      if (commandTemplateSaveTimerRef.current) {
        clearTimeout(commandTemplateSaveTimerRef.current)
      }
    }
  }, [])

  useEffect(() => {
    if (loggedIn) {
      loadDaemons()
      fetchRequests()
    }
  }, [loggedIn, loadDaemons, fetchRequests])

  useEffect(() => {
    if (selectedDaemonId) {
      setInstancePage(1)
      loadInstances(1)
    }
  }, [selectedDaemonId, loadInstances])

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
    setDaemons([])
    setInstances([])
    setSelectedDaemonId('')
    setSelectedInstanceId('')
    setShowDaemonDialog(false)
    setShowInstanceDialog(false)
    setShowCommandTemplateDialog(false)
    setCommandTemplateInput('')
  }

  const handleSelectInstance = async (instanceId) => {
    setSelectedInstanceId(instanceId)
    setShowInstanceDialog(false)
    
    // Save the selection
    if (selectedDaemonId && instanceId) {
      try {
        await apiClient.saveSelection(adminToken, selectedDaemonId, instanceId)
        console.log('Selection saved')
      } catch (err) {
        console.error('Failed to save selection:', err)
        setError(`保存配置失败: ${err.message}`)
      }
    }
  }

  const handleSelectDaemon = (daemonId) => {
    setSelectedDaemonId(daemonId)
    setShowDaemonDialog(false)
  }

  const handleOpenCommandTemplateDialog = () => {
    setCommandTemplateInput(config?.mcsm?.commandTemplate || 'authme register {username} {password}')
    setCommandTemplateSaveStatus('idle')
    setShowCommandTemplateDialog(true)
  }

  const handleSaveCommandTemplate = useCallback(async () => {
    if (!adminToken) return
    const trimmed = commandTemplateInput.trim()
    if (!trimmed) {
      setError('命令模板不能为空')
      return
    }
    setCommandTemplateSaveStatus('saving')
    setError(null)
    try {
      const response = await apiClient.updateCommandTemplate(adminToken, trimmed)
      // Update config directly with the saved value
      setConfig(prevConfig => ({
        ...prevConfig,
        mcsm: {
          ...prevConfig?.mcsm,
          commandTemplate: response.commandTemplate || trimmed,
        },
      }))
      setCommandTemplateSaveStatus('saved')
      // Reset status after 2 seconds
      setTimeout(() => setCommandTemplateSaveStatus('idle'), 2000)
    } catch (err) {
      setError(err.message)
      setCommandTemplateSaveStatus('error')
    }
  }, [adminToken, commandTemplateInput])

  // Auto-save command template with debouncing
  useEffect(() => {
    if (!showCommandTemplateDialog || !adminToken) return
    
    // Clear previous timer
    if (commandTemplateSaveTimerRef.current) {
      clearTimeout(commandTemplateSaveTimerRef.current)
    }

    const trimmed = commandTemplateInput.trim()
    if (!trimmed) return

    // Check if content has changed from the config
    if (config?.mcsm?.commandTemplate === trimmed) {
      setCommandTemplateSaveStatus('idle')
      return
    }

    // Set timer for auto-save (500ms debounce)
    commandTemplateSaveTimerRef.current = setTimeout(() => {
      handleSaveCommandTemplate()
    }, 500)

    return () => {
      if (commandTemplateSaveTimerRef.current) {
        clearTimeout(commandTemplateSaveTimerRef.current)
      }
    }
  }, [commandTemplateInput, showCommandTemplateDialog, adminToken, config?.mcsm?.commandTemplate, handleSaveCommandTemplate])

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
      daemonId: selectedDaemonId || config?.mcsm?.defaultDaemonId || '',
      instanceId: selectedInstanceId || config?.mcsm?.defaultInstanceId || '',
    }),
    [selectedDaemonId, selectedInstanceId, config],
  )

  if (!loggedIn) {
    return (
      <div className="page-shell">
        <div className="panel login-card">
          <h1>管理员登录</h1>
          <p className="panel-subtitle">使用 MCSManager 账号生成的 API Key 登录审核面板。</p>
          <form className="form-grid" onSubmit={handleLogin}>
            <div className="field" style={{ gridColumn: '1 / -1' }}>
              <label htmlFor="admin-token">管理Key</label>
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
            <h3 style={{ marginTop: 0 }}>节点与实例选择</h3>
            
            <div style={{ marginBottom: '1rem' }}>
              <label style={{ display: 'block', marginBottom: '0.5rem' }}>选择节点</label>
              <button
                type="button"
                onClick={() => setShowDaemonDialog(true)}
                disabled={loadingDaemons || daemons.length === 0}
                style={{
                  width: '100%',
                  padding: '0.5rem',
                  textAlign: 'left',
                  backgroundColor: 'rgba(30,41,59,0.8)',
                  border: '1px solid rgba(148,163,184,0.3)',
                  borderRadius: '4px',
                  color: 'rgba(226,232,240,1)',
                  cursor: loadingDaemons || daemons.length === 0 ? 'not-allowed' : 'pointer',
                  opacity: loadingDaemons || daemons.length === 0 ? 0.6 : 1,
                }}
              >
                {loadingDaemons ? '加载中...' : selectedDaemonId ? daemons.find(d => d.id === selectedDaemonId)?.name || '未找到' : daemons.length === 0 ? '无可用节点' : '点击选择节点'}
              </button>
              {showDaemonDialog && (
                <div
                  style={{
                    position: 'fixed',
                    inset: 0,
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 1000,
                  }}
                  onClick={() => setShowDaemonDialog(false)}
                >
                  <div
                    style={{
                      backgroundColor: 'rgba(15,23,42,0.95)',
                      border: '1px solid rgba(148,163,184,0.3)',
                      borderRadius: '8px',
                      padding: '1.5rem',
                      maxHeight: '70vh',
                      overflowY: 'auto',
                      minWidth: '300px',
                      maxWidth: '500px',
                    }}
                    onClick={(e) => e.stopPropagation()}
                  >
                    <h3 style={{ marginTop: 0, marginBottom: '1rem' }}>选择节点</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                      {daemons.map((daemon) => (
                        <button
                          key={daemon.id}
                          type="button"
                          onClick={() => handleSelectDaemon(daemon.id)}
                          style={{
                            padding: '0.75rem',
                            textAlign: 'left',
                            backgroundColor: daemon.id === selectedDaemonId ? 'rgba(59,130,246,0.2)' : 'rgba(30,41,59,0.5)',
                            border: daemon.id === selectedDaemonId ? '1px solid rgb(59,130,246)' : '1px solid rgba(148,163,184,0.2)',
                            borderRadius: '4px',
                            color: 'rgba(226,232,240,1)',
                            cursor: 'pointer',
                            transition: 'all 0.2s',
                          }}
                          onMouseEnter={(e) => {
                            e.target.style.backgroundColor = daemon.id === selectedDaemonId ? 'rgba(59,130,246,0.3)' : 'rgba(30,41,59,0.7)'
                          }}
                          onMouseLeave={(e) => {
                            e.target.style.backgroundColor = daemon.id === selectedDaemonId ? 'rgba(59,130,246,0.2)' : 'rgba(30,41,59,0.5)'
                          }}
                        >
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                            <div>
                              <div>{daemon.name} {daemon.available ? '✓' : '✗'}</div>
                              <div style={{ fontSize: '0.85rem', color: 'rgba(148,163,184,0.7)', marginTop: '0.25rem' }}>
                                {daemon.id}
                              </div>
                            </div>
                          </div>
                        </button>
                      ))}
                    </div>
                    <button
                      type="button"
                      onClick={() => setShowDaemonDialog(false)}
                      style={{
                        marginTop: '1rem',
                        width: '100%',
                        padding: '0.5rem',
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        border: '1px solid rgba(59,130,246,0.5)',
                        borderRadius: '4px',
                        color: 'rgba(59,130,246,1)',
                        cursor: 'pointer',
                      }}
                    >
                      关闭
                    </button>
                  </div>
                </div>
              )}
            </div>

            <div style={{ marginBottom: '1.5rem' }}>
              <label style={{ display: 'block', marginBottom: '0.5rem' }}>选择实例</label>
              <button
                type="button"
                onClick={() => {
                  setShowInstanceDialog(true)
                  if (!instances.length && selectedDaemonId && !loadingInstances) {
                    loadInstances(1)
                  }
                }}
                disabled={loadingInstances || !selectedDaemonId}
                style={{
                  width: '100%',
                  padding: '0.5rem',
                  textAlign: 'left',
                  backgroundColor: 'rgba(30,41,59,0.8)',
                  border: '1px solid rgba(148,163,184,0.3)',
                  borderRadius: '4px',
                  color: 'rgba(226,232,240,1)',
                  cursor: loadingInstances || !selectedDaemonId ? 'not-allowed' : 'pointer',
                  opacity: loadingInstances || !selectedDaemonId ? 0.6 : 1,
                }}
              >
                {!selectedDaemonId ? '请先选择节点' : loadingInstances ? '加载中...' : selectedInstanceId ? instances.find(i => i.id === selectedInstanceId)?.name || '未找到' : instances.length === 0 ? '点击加载实例' : '点击选择实例'}
              </button>
              {showInstanceDialog && (
                <div
                  style={{
                    position: 'fixed',
                    inset: 0,
                    backgroundColor: 'rgba(0,0,0,0.7)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 1000,
                  }}
                  onClick={() => setShowInstanceDialog(false)}
                >
                  <div
                    style={{
                      backgroundColor: 'rgba(15,23,42,0.95)',
                      border: '1px solid rgba(148,163,184,0.3)',
                      borderRadius: '8px',
                      padding: '1.5rem',
                      maxHeight: '70vh',
                      overflowY: 'auto',
                      minWidth: '300px',
                      maxWidth: '500px',
                      display: 'flex',
                      flexDirection: 'column',
                    }}
                    onClick={(e) => e.stopPropagation()}
                  >
                    <h3 style={{ marginTop: 0, marginBottom: '1rem' }}>选择实例 (第 {instancePage} / {instancesMaxPage} 页)</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', flexGrow: 1 }}>
                      {loadingInstances ? (
                        <div style={{ textAlign: 'center', padding: '1rem', color: 'rgba(148,163,184,0.7)' }}>
                          加载中...
                        </div>
                      ) : instances.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: '1rem', color: 'rgba(148,163,184,0.7)' }}>
                          无可用实例
                        </div>
                      ) : (
                        instances.map((instance) => (
                          <button
                            key={instance.id}
                            type="button"
                            onClick={() => handleSelectInstance(instance.id)}
                            style={{
                              padding: '0.75rem',
                              textAlign: 'left',
                              backgroundColor: instance.id === selectedInstanceId ? 'rgba(59,130,246,0.2)' : 'rgba(30,41,59,0.5)',
                              border: instance.id === selectedInstanceId ? '1px solid rgb(59,130,246)' : '1px solid rgba(148,163,184,0.2)',
                              borderRadius: '4px',
                              color: 'rgba(226,232,240,1)',
                              cursor: 'pointer',
                              transition: 'all 0.2s',
                            }}
                            onMouseEnter={(e) => {
                              e.target.style.backgroundColor = instance.id === selectedInstanceId ? 'rgba(59,130,246,0.3)' : 'rgba(30,41,59,0.7)'
                            }}
                            onMouseLeave={(e) => {
                              e.target.style.backgroundColor = instance.id === selectedInstanceId ? 'rgba(59,130,246,0.2)' : 'rgba(30,41,59,0.5)'
                            }}
                          >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                              <div>
                                <div>{instance.name}</div>
                                <div style={{ fontSize: '0.85rem', color: 'rgba(148,163,184,0.7)', marginTop: '0.25rem' }}>
                                  {instance.id}
                                </div>
                              </div>
                            </div>
                          </button>
                        ))
                      )}
                    </div>
                    
                    {instancesMaxPage > 1 && (
                      <div style={{ display: 'flex', gap: '0.5rem', marginTop: '1rem', justifyContent: 'center' }}>
                        <button
                          type="button"
                          onClick={() => loadInstances(instancePage - 1)}
                          disabled={instancePage <= 1}
                          style={{
                            padding: '0.5rem 1rem',
                            backgroundColor: 'rgba(59,130,246,0.2)',
                            border: '1px solid rgba(59,130,246,0.5)',
                            borderRadius: '4px',
                            color: 'rgba(59,130,246,1)',
                            cursor: instancePage <= 1 ? 'not-allowed' : 'pointer',
                            opacity: instancePage <= 1 ? 0.5 : 1,
                          }}
                        >
                          上一页
                        </button>
                        <div style={{ padding: '0.5rem', color: 'rgba(148,163,184,0.7)' }}>
                          第 {instancePage} / {instancesMaxPage} 页
                        </div>
                        <button
                          type="button"
                          onClick={() => loadInstances(instancePage + 1)}
                          disabled={instancePage >= instancesMaxPage}
                          style={{
                            padding: '0.5rem 1rem',
                            backgroundColor: 'rgba(59,130,246,0.2)',
                            border: '1px solid rgba(59,130,246,0.5)',
                            borderRadius: '4px',
                            color: 'rgba(59,130,246,1)',
                            cursor: instancePage >= instancesMaxPage ? 'not-allowed' : 'pointer',
                            opacity: instancePage >= instancesMaxPage ? 0.5 : 1,
                          }}
                        >
                          下一页
                        </button>
                      </div>
                    )}
                    
                    <button
                      type="button"
                      onClick={() => setShowInstanceDialog(false)}
                      style={{
                        marginTop: '1rem',
                        width: '100%',
                        padding: '0.5rem',
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        border: '1px solid rgba(59,130,246,0.5)',
                        borderRadius: '4px',
                        color: 'rgba(59,130,246,1)',
                        cursor: 'pointer',
                      }}
                    >
                      关闭
                    </button>
                  </div>
                </div>
              )}
            </div>

            <h3>面板状态</h3>
            <p style={{ color: 'rgba(148,163,184,0.85)' }}>
              <strong>当前节点：</strong>
              <div style={{ color: 'rgba(148,163,184,0.7)', fontSize: '0.9rem' }}>
                {selectedDaemonId || config?.mcsm?.defaultDaemonId || '未配置'}
              </div>
              <strong>当前实例：</strong>
              <div style={{ color: 'rgba(148,163,184,0.7)', fontSize: '0.9rem' }}>
                {selectedInstanceId || config?.mcsm?.defaultInstanceId || '未配置'}
              </div>
              <strong style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '0.5rem' }}>
                <span>命令模板：</span>
                <button
                  type="button"
                  onClick={handleOpenCommandTemplateDialog}
                  className="btn secondary"
                  style={{ padding: '0.25rem 0.75rem', fontSize: '0.85rem' }}
                >
                  编辑
                </button>
              </strong>
              <span className="inline-code" style={{ display: 'block', marginTop: '0.25rem', wordBreak: 'break-all' }}>
                {config?.mcsm?.commandTemplate || 'authme register {username} {password}'}
              </span>
            </p>
            <button className="btn secondary" type="button" onClick={fetchRequests} disabled={loading}>
              {loading ? '同步中...' : '刷新列表'}
            </button>

            {showCommandTemplateDialog && (
              <div
                style={{
                  position: 'fixed',
                  inset: 0,
                  backgroundColor: 'rgba(0,0,0,0.7)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  zIndex: 1000,
                }}
                onClick={() => setShowCommandTemplateDialog(false)}
              >
                <div
                  style={{
                    backgroundColor: 'rgba(15,23,42,0.95)',
                    border: '1px solid rgba(148,163,184,0.3)',
                    borderRadius: '8px',
                    padding: '1.5rem',
                    maxWidth: '600px',
                    width: '90%',
                    maxHeight: '80vh',
                    overflowY: 'auto',
                  }}
                  onClick={(e) => e.stopPropagation()}
                >
                  <h3 style={{ marginTop: 0, marginBottom: '1rem' }}>
                    编辑命令模板
                    {commandTemplateSaveStatus === 'saving' && (
                      <span style={{ fontSize: '0.85rem', color: 'rgba(248,113,113,1)', marginLeft: '0.5rem' }}>
                        保存中...
                      </span>
                    )}
                    {commandTemplateSaveStatus === 'saved' && (
                      <span style={{ fontSize: '0.85rem', color: 'rgba(34,197,94,1)', marginLeft: '0.5rem' }}>
                        ✓ 已保存
                      </span>
                    )}
                    {commandTemplateSaveStatus === 'error' && (
                      <span style={{ fontSize: '0.85rem', color: 'rgba(239,68,68,1)', marginLeft: '0.5rem' }}>
                        ✗ 保存失败
                      </span>
                    )}
                  </h3>
                  <div style={{ marginBottom: '1rem' }}>
                    <label style={{ display: 'block', marginBottom: '0.5rem', color: 'rgba(226,232,240,1)' }}>
                      命令模板
                    </label>
                    <textarea
                      value={commandTemplateInput}
                      onChange={(e) => setCommandTemplateInput(e.target.value)}
                      placeholder="例如: authme register {username} {password}"
                      style={{
                        width: '100%',
                        padding: '0.75rem',
                        backgroundColor: 'rgba(30,41,59,0.8)',
                        border: '1px solid rgba(148,163,184,0.3)',
                        borderRadius: '4px',
                        color: 'rgba(226,232,240,1)',
                        fontFamily: 'monospace',
                        minHeight: '100px',
                        resize: 'vertical',
                        boxSizing: 'border-box',
                      }}
                    />
                    <div style={{ fontSize: '0.85rem', color: 'rgba(148,163,184,0.7)', marginTop: '0.5rem' }}>
                      支持的占位符：
                      <ul style={{ margin: '0.5rem 0', paddingLeft: '1.5rem' }}>
                        <li><code style={{ backgroundColor: 'rgba(30,41,59,0.8)', padding: '0.15rem 0.4rem' }}>{'{'}{'{'}username{'}'}{''}</code> - 用户名</li>
                        <li><code style={{ backgroundColor: 'rgba(30,41,59,0.8)', padding: '0.15rem 0.4rem' }}>{'{'}{'{'}password{'}'}{''}</code> - 密码</li>
                      </ul>
                    </div>
                  </div>
                  <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
                    <button
                      type="button"
                      onClick={() => setShowCommandTemplateDialog(false)}
                      style={{
                        padding: '0.5rem 1rem',
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        border: '1px solid rgba(59,130,246,0.5)',
                        borderRadius: '4px',
                        color: 'rgba(59,130,246,1)',
                        cursor: 'pointer',
                      }}
                    >
                      关闭
                    </button>
                  </div>
                </div>
              </div>
            )}
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
