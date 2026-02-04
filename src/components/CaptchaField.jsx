import { useEffect, useRef } from 'react'

const SCRIPT_CACHE = {}

function loadScript(src, attributes = {}) {
  if (SCRIPT_CACHE[src]) return SCRIPT_CACHE[src]

  SCRIPT_CACHE[src] = new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = src
    script.async = true
    Object.entries(attributes).forEach(([key, value]) => {
      script.setAttribute(key, value)
    })
    script.onload = () => resolve()
    script.onerror = (err) => reject(err)
    document.body.appendChild(script)
  })

  return SCRIPT_CACHE[src]
}

const PROVIDERS = {
  recaptcha_v2: {
    script: 'https://www.google.com/recaptcha/api.js?render=explicit',
    render: (container, siteKey, onToken) => {
      if (!window.grecaptcha) return null
      let widgetId = null
      const renderWidget = () => {
        widgetId = window.grecaptcha.render(container, {
          sitekey: siteKey,
          callback: (token) => onToken(token || null),
          'expired-callback': () => onToken(null),
          'error-callback': () => onToken(null),
        })
      }

      if (window.grecaptcha.render) {
        window.grecaptcha.ready(renderWidget)
      } else {
        renderWidget()
      }

      return () => {
        if (widgetId !== null && window.grecaptcha?.reset) {
          window.grecaptcha.reset(widgetId)
        }
      }
    },
  },
  hcaptcha: {
    script: 'https://js.hcaptcha.com/1/api.js?render=explicit',
    render: (container, siteKey, onToken) => {
      if (!window.hcaptcha) return null
      const widgetId = window.hcaptcha.render(container, {
        sitekey: siteKey,
        callback: (token) => onToken(token || null),
        'expired-callback': () => onToken(null),
      })
      return () => {
        if (window.hcaptcha && typeof widgetId === 'number') {
          window.hcaptcha.reset(widgetId)
        }
      }
    },
  },
  turnstile: {
    script: 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
    render: (container, siteKey, onToken) => {
      if (!window.turnstile) return null
      const widgetId = window.turnstile.render(container, {
        sitekey: siteKey,
        callback: (token) => onToken(token || null),
        'error-callback': () => onToken(null),
        'expired-callback': () => onToken(null),
      })
      return () => {
        if (window.turnstile?.reset && widgetId) {
          window.turnstile.reset(widgetId)
        }
      }
    },
  },
}

export default function CaptchaField({
  provider,
  siteKey,
  simpleChallenge,
  onSimpleAnswer,
  onToken,
  disabled = false,
}) {
  const containerRef = useRef(null)
  const cleanupRef = useRef(null)

  useEffect(() => {
    if (!containerRef.current || provider === 'simple_math') return undefined
    const def = PROVIDERS[provider]
    if (!def) return undefined

    let cancelled = false

    loadScript(def.script)
      .then(() => {
        if (cancelled) return
        if (!siteKey) {
          onToken(null)
          return
        }
        cleanupRef.current = def.render(containerRef.current, siteKey, onToken)
      })
      .catch(() => {
        onToken(null)
      })

    return () => {
      cancelled = true
      if (cleanupRef.current) {
        cleanupRef.current()
        cleanupRef.current = null
      }
    }
  }, [provider, siteKey, onToken])

  if (!provider || provider === 'none') {
    return null
  }

  if (provider === 'simple_math') {
    return (
      <div className="captchabox">
        <p>人机验证：{simpleChallenge?.question || '正在加载题目...'}</p>
        <div className="field" style={{ marginTop: '0.8rem' }}>
          <label htmlFor="captcha-answer">请输入答案</label>
          <input
            id="captcha-answer"
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder="例如：42"
            onChange={(e) => onSimpleAnswer(e.target.value)}
            disabled={disabled || !simpleChallenge}
          />
        </div>
      </div>
    )
  }

  if (!siteKey) {
    return (
      <div className="alert error">
        <strong>缺少 {provider} 的站点密钥</strong>
        <p>请先在后台配置站点密钥，否则无法完成注册验证。</p>
      </div>
    )
  }

  return (
    <div className="captchabox">
      <p>完成验证码验证后再提交</p>
      <div ref={containerRef} className="captcha-container" />
    </div>
  )
}
