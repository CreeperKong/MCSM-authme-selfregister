import { useCallback, useEffect, useMemo, useState } from 'react'
import CaptchaField from '../components/CaptchaField.jsx'
import { apiClient } from '../api/client.js'

const initialForm = {
  username: '',
  password: '',
  passwordConfirm: '',
  email: '',
  note: '',
}

export default function RegisterPage() {
  const [form, setForm] = useState(initialForm)
  const [config, setConfig] = useState(null)
  const [challenge, setChallenge] = useState(null)
  const [simpleAnswer, setSimpleAnswer] = useState('')
  const [captchaToken, setCaptchaToken] = useState(null)
  const [loading, setLoading] = useState(false)
  const [feedback, setFeedback] = useState(null)

  const provider = config?.captcha?.provider || 'off'

  const loadConfig = useCallback(async () => {
    try {
      const data = await apiClient.fetchConfig()
      setConfig(data)
    } catch (error) {
      setFeedback({ type: 'error', message: error.message })
    }
  }, [])

  useEffect(() => {
    loadConfig()
  }, [loadConfig])

  useEffect(() => {
    setCaptchaToken(null)
    setSimpleAnswer('')
  }, [provider])

  const handleChange = (field) => (event) => {
    setForm((prev) => ({ ...prev, [field]: event.target.value }))
  }

  const resetForm = () => {
    setForm(initialForm)
    setSimpleAnswer('')
    setCaptchaToken(null)
  }

  const captchaReady = useMemo(() => {
    if (provider === 'off' || provider === 'none') return true
    return Boolean(captchaToken)
  }, [provider, captchaToken])

  const handleSubmit = async (event) => {
    event.preventDefault()
    setFeedback(null)

    if (form.password !== form.passwordConfirm) {
      setFeedback({ type: 'error', message: '两次输入的密码不一致。' })
      return
    }

    if (!captchaReady) {
      setFeedback({ type: 'error', message: '请先完成验证码。' })
      return
    }

    setLoading(true)
    try {
      const payload = {
        username: form.username.trim(),
        password: form.password,
        password_confirm: form.passwordConfirm,
        email: form.email.trim(),
        note: form.note.trim(),
        captcha: {
          provider,
          token: captchaToken,
          challengeId: challenge?.id,
          answer: simpleAnswer,
        },
      }
      const data = await apiClient.submitRegistration(payload)
      setFeedback({ type: 'success', message: data.message || '提交成功，请等待管理员审核。' })
      resetForm()
    } catch (error) {
      setFeedback({ type: 'error', message: error.message || '提交失败，请稍后再试。' })
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="page-shell">
      <div className="panel">
        <h1>AuthMe 自助注册</h1>
        <p className="panel-subtitle">
          提交注册请求后，管理员审核通过即会在指定实例中执行 <span className="inline-code">authme register</span> 命令。
        </p>

        <form className="form-grid" onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="username">游戏 ID</label>
            <input
              id="username"
              required
              value={form.username}
              onChange={handleChange('username')}
              placeholder="只允许字母数字下划线"
              disabled={loading}
            />
          </div>

          <div className="field">
            <label htmlFor="email">邮箱</label>
            <input
              id="email"
              type="email"
              required
              value={form.email}
              onChange={handleChange('email')}
              placeholder="用于接收审核状态"
              disabled={loading}
            />
          </div>

          <div className="field">
            <label htmlFor="password">密码</label>
            <input
              id="password"
              type="password"
              required
              value={form.password}
              onChange={handleChange('password')}
              placeholder="至少 8 位，大小写 + 数字"
              disabled={loading}
            />
          </div>

          <div className="field">
            <label htmlFor="passwordConfirm">重复密码</label>
            <input
              id="passwordConfirm"
              type="password"
              required
              value={form.passwordConfirm}
              onChange={handleChange('passwordConfirm')}
              placeholder="再次输入密码"
              disabled={loading}
            />
          </div>

          <div className="field" style={{ gridColumn: '1 / -1' }}>
            <label htmlFor="note">补充信息 (可选)</label>
            <textarea
              id="note"
              value={form.note}
              onChange={handleChange('note')}
              placeholder="可填写服务器 ID、邀请码等"
              disabled={loading}
            />
          </div>

          <div style={{ gridColumn: '1 / -1' }}>
            <CaptchaField
              provider={provider}
              siteKey={config?.captcha?.siteKey}
              simpleChallenge={challenge}
              onSimpleAnswer={setSimpleAnswer}
              onToken={setCaptchaToken}
              disabled={loading}
            />
          </div>

          <div className="btn-row" style={{ gridColumn: '1 / -1' }}>
            <button className="btn" type="submit" disabled={loading || !captchaReady}>
              {loading ? '提交中...' : '提交审核请求'}
            </button>
            {provider === 'simple_math' && (
              <button
                type="button"
                className="btn secondary"
                onClick={loadChallenge}
                disabled={loading}
              >
                刷新题目
              </button>
            )}
          </div>
        </form>

        {feedback && (
          <div className={`alert ${feedback.type}`} style={{ marginTop: '1.5rem' }}>
            {feedback.message}
          </div>
        )}

        <p style={{ marginTop: '1.5rem', color: 'rgba(148,163,184,0.8)' }}>
          管理员入口： <a href="/admin">/admin</a>
        </p>
      </div>
    </div>
  )
}
