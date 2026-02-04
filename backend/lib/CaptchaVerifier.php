<?php
class CaptchaVerifier
{
    private array $config;
    private SimpleCaptchaStore $store;

    public function __construct(array $config, SimpleCaptchaStore $store)
    {
        $this->config = $config;
        $this->store = $store;
    }

    public function createChallenge(): array
    {
        return $this->store->createChallenge();
    }

    public function verify(array $payload, ?string $ipAddress = null): void
    {
        $provider = strtolower($this->config['provider'] ?? 'simple_math');

        if ($provider === 'none') {
            return;
        }

        if ($provider === 'simple_math') {
            $challengeId = $payload['challengeId'] ?? $payload['challenge_id'] ?? null;
            $answer = $payload['answer'] ?? null;
            $this->store->validate((string) $challengeId, (string) $answer);
            return;
        }

        $token = $payload['token'] ?? $payload['response'] ?? null;
        if (!$token) {
            throw new HttpException(400, '验证码令牌缺失');
        }

        switch ($provider) {
            case 'recaptcha_v2':
                $this->verifyRemote(
                    'https://www.google.com/recaptcha/api/siteverify',
                    $token,
                    $this->config['recaptcha']['secret_key'] ?? '',
                    $ipAddress
                );
                break;
            case 'hcaptcha':
                $this->verifyRemote(
                    'https://hcaptcha.com/siteverify',
                    $token,
                    $this->config['hcaptcha']['secret_key'] ?? '',
                    $ipAddress
                );
                break;
            case 'turnstile':
                $this->verifyRemote(
                    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                    $token,
                    $this->config['turnstile']['secret_key'] ?? '',
                    $ipAddress
                );
                break;
            default:
                throw new HttpException(400, sprintf('不支持的验证码提供商：%s', $provider));
        }
    }

    private function verifyRemote(string $endpoint, string $token, string $secret, ?string $ipAddress): void
    {
        if (!$secret) {
            throw new HttpException(500, '验证码密钥未配置');
        }

        $postData = http_build_query(array_filter([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ipAddress,
        ]));

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new HttpException(502, '验证码服务不可用', ['error' => $error]);
        }

        $decoded = json_decode($response, true);
        if (!$decoded || empty($decoded['success'])) {
            throw new HttpException(400, '验证码验证失败', ['response' => $decoded]);
        }
    }
}
