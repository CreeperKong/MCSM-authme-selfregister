<?php
class RegistrationService
{
    private PDO $pdo;
    private Encryption $encryption;
    private ?MCSMClient $mcsm;
    private string $commandTemplate;

    public function __construct(PDO $pdo, Encryption $encryption, ?MCSMClient $mcsm, array $config)
    {
        $this->pdo = $pdo;
        $this->encryption = $encryption;
        $this->mcsm = $mcsm;
        $this->commandTemplate = $config['mcsm']['command_template'] ?? 'authme register {username} {password} {password}';
    }

    public function createRequest(array $payload, string $ipAddress = null): array
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirm'] ?? '');
        $note = trim((string) ($payload['note'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $username)) {
            throw new HttpException(400, '用户名仅允许 3-16 位字母、数字或下划线');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(400, '邮箱格式无效');
        }

        if (strlen($password) < 8) {
            throw new HttpException(400, '密码至少 8 位');
        }

        if (!hash_equals($password, $passwordConfirm)) {
            throw new HttpException(400, '两次密码输入不一致');
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM registration_requests WHERE username = :username AND status = "pending"');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            throw new HttpException(409, '该用户名已提交审核，请等待处理');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $encrypted = $this->encryption->encrypt($password);

        $insert = $this->pdo->prepare('INSERT INTO registration_requests (username, email, password_hash, password_payload, note, status, ip_address) VALUES (:username, :email, :password_hash, :payload, :note, "pending", :ip)');
        $insert->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':payload' => $encrypted,
            ':note' => $note,
            ':ip' => $ipAddress,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'message' => '提交成功，请等待管理员审核',
        ];
    }

    public function listRequests(string $status): array
    {
        $allowed = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $stmt = $this->pdo->prepare('SELECT * FROM registration_requests WHERE status = :status ORDER BY requested_at DESC LIMIT 200');
        $stmt->execute([':status' => $status]);
        $rows = $stmt->fetchAll();

        return array_map([$this, 'transform'], $rows);
    }

    public function approve(int $id, string $daemonId, string $instanceId, ?string $notes, string $processedBy = 'admin'): array
    {
        if (!$this->mcsm) {
            throw new HttpException(500, 'MCSManager 未配置');
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT * FROM registration_requests WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new HttpException(404, '请求不存在');
            }

            if ($row['status'] !== 'pending') {
                throw new HttpException(409, '请求已被处理');
            }

            $password = $this->encryption->decrypt($row['password_payload']);
            $command = $this->buildCommand($row['username'], $password);

            $this->mcsm->sendCommand($daemonId, $instanceId, $command);

            $update = $this->pdo->prepare('UPDATE registration_requests SET status = "approved", mcsm_daemon_id = :daemon, mcsm_instance_id = :instance, processed_at = NOW(), processed_by = :by, admin_notes = :notes WHERE id = :id');
            $update->execute([
                ':daemon' => $daemonId,
                ':instance' => $instanceId,
                ':by' => $processedBy,
                ':notes' => $notes,
                ':id' => $id,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->find($id);
    }

    public function reject(int $id, string $reason, string $processedBy = 'admin'): array
    {
        if (!$reason) {
            throw new HttpException(400, '拒绝理由不能为空');
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT * FROM registration_requests WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new HttpException(404, '请求不存在');
            }
            if ($row['status'] !== 'pending') {
                throw new HttpException(409, '请求已被处理');
            }

            $update = $this->pdo->prepare('UPDATE registration_requests SET status = "rejected", rejection_reason = :reason, processed_at = NOW(), processed_by = :by WHERE id = :id');
            $update->execute([
                ':reason' => $reason,
                ':by' => $processedBy,
                ':id' => $id,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->find($id);
    }

    private function find(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registration_requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new HttpException(404, '请求不存在');
        }
        return $this->transform($row);
    }

    private function transform(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'status' => $row['status'],
            'note' => $row['note'] ?? null,
            'requested_at' => $this->formatTimestamp($row['requested_at']),
            'processed_at' => $this->formatTimestamp($row['processed_at']),
            'processed_by' => $row['processed_by'],
            'mcsm_daemon_id' => $row['mcsm_daemon_id'],
            'mcsm_instance_id' => $row['mcsm_instance_id'],
            'admin_notes' => $row['admin_notes'],
            'rejection_reason' => $row['rejection_reason'],
        ];
    }

    private function buildCommand(string $username, string $password): string
    {
        return strtr($this->commandTemplate, [
            '{username}' => $username,
            '{password}' => $password,
        ]);
    }

    private function formatTimestamp(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
    }
}
