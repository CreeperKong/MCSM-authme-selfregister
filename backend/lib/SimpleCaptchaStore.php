<?php
class SimpleCaptchaStore
{
    private PDO $pdo;
    private int $ttl;

    public function __construct(PDO $pdo, int $ttlSeconds)
    {
        $this->pdo = $pdo;
        $this->ttl = max(60, $ttlSeconds);
    }

    public function createChallenge(): array
    {
        $a = random_int(10, 99);
        $b = random_int(10, 99);
        $question = sprintf('%d + %d = ?', $a, $b);
        $answer = (string) ($a + $b);

        $id = bin2hex(random_bytes(10));
        $expiresAt = (new DateTimeImmutable(sprintf('+%d seconds', $this->ttl)))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO captcha_challenges (id, answer_hash, expires_at) VALUES (:id, :hash, :expires_at)');
        $stmt->execute([
            ':id' => $id,
            ':hash' => password_hash($answer, PASSWORD_DEFAULT),
            ':expires_at' => $expiresAt,
        ]);

        $this->cleanup();

        return [
            'id' => $id,
            'question' => $question,
            'expiresAt' => $expiresAt,
        ];
    }

    public function validate(string $challengeId, string $answer): void
    {
        if (!$challengeId || !$answer) {
            throw new HttpException(400, '验证码不能为空');
        }

        $stmt = $this->pdo->prepare('SELECT answer_hash, expires_at FROM captcha_challenges WHERE id = :id');
        $stmt->execute([':id' => $challengeId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new HttpException(400, '验证码不存在或已过期');
        }

        if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable()) {
            $this->delete($challengeId);
            throw new HttpException(400, '验证码已过期');
        }

        if (!password_verify(trim($answer), $row['answer_hash'])) {
            throw new HttpException(400, '验证码错误');
        }

        $this->delete($challengeId);
    }

    private function delete(string $challengeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM captcha_challenges WHERE id = :id');
        $stmt->execute([':id' => $challengeId]);
    }

    private function cleanup(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM captcha_challenges WHERE expires_at < NOW()');
        $stmt->execute();
    }
}
