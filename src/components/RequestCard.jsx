import { useEffect, useState } from 'react'

const statusLabel = {
  pending: '待审核',
  approved: '已批准',
  rejected: '已拒绝',
}

export default function RequestCard({
  request,
  defaults = {},
  onApprove,
  onReject,
  submitting,
}) {
  const [daemonId, setDaemonId] = useState(request.mcsm_daemon_id || defaults.daemonId || '')
  const [instanceId, setInstanceId] = useState(request.mcsm_instance_id || defaults.instanceId || '')
  const [notes, setNotes] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')

  useEffect(() => {
    setDaemonId(request.mcsm_daemon_id || defaults.daemonId || '')
    setInstanceId(request.mcsm_instance_id || defaults.instanceId || '')
  }, [request, defaults])

  const disabled = submitting?.id === request.id

  return (
    <div className="request-card">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h3 style={{ margin: 0 }}>{request.username}</h3>
          <p style={{ margin: '0.2rem 0 0', color: 'rgba(226,232,240,0.7)' }}>{request.email}</p>
        </div>
        <span className={`status-pill ${request.status}`}>
          {statusLabel[request.status] || request.status}
        </span>
      </header>

      <div className="request-meta">
        <div>
          <strong>提交时间</strong>
          <div>{new Date(request.requested_at).toLocaleString()}</div>
        </div>
        {request.processed_at && (
          <div>
            <strong>处理时间</strong>
            <div>{new Date(request.processed_at).toLocaleString()}</div>
          </div>
        )}
        {request.rejection_reason && (
          <div>
            <strong>拒绝理由</strong>
            <div>{request.rejection_reason}</div>
          </div>
        )}
      </div>

      {request.status === 'pending' && (
        <div className="request-actions">
          <div className="field" style={{ flex: 1, minWidth: 180 }}>
            <label htmlFor={`daemon-${request.id}`}>节点 ID</label>
            <input
              id={`daemon-${request.id}`}
              value={daemonId}
              onChange={(e) => setDaemonId(e.target.value)}
              placeholder="daemon-uuid"
              disabled={disabled}
            />
          </div>
          <div className="field" style={{ flex: 1, minWidth: 180 }}>
            <label htmlFor={`instance-${request.id}`}>实例 ID</label>
            <input
              id={`instance-${request.id}`}
              value={instanceId}
              onChange={(e) => setInstanceId(e.target.value)}
              placeholder="instance-uuid"
              disabled={disabled}
            />
          </div>
          <div className="field" style={{ flexBasis: '100%' }}>
            <label htmlFor={`notes-${request.id}`}>处理备注</label>
            <textarea
              id={`notes-${request.id}`}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="可选，记录下发命令的附加说明"
              disabled={disabled}
            />
          </div>
          <div className="field" style={{ flexBasis: '100%' }}>
            <label htmlFor={`reject-${request.id}`}>拒绝理由</label>
            <input
              id={`reject-${request.id}`}
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              placeholder="若拒绝则填写"
              disabled={disabled}
            />
          </div>
          <div className="btn-row">
            <button
              type="button"
              className="btn"
              disabled={disabled || !daemonId || !instanceId}
              onClick={() => onApprove({
                requestId: request.id,
                daemonId,
                instanceId,
                notes,
              })}
            >
              {disabled && submitting?.action === 'approve' ? '执行中...' : '批准并执行'}
            </button>
            <button
              type="button"
              className="btn danger"
              disabled={disabled || !rejectionReason}
              onClick={() => onReject({
                requestId: request.id,
                reason: rejectionReason,
              })}
            >
              {disabled && submitting?.action === 'reject' ? '提交中...' : '拒绝请求'}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
