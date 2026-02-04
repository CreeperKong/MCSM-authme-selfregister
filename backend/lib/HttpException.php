<?php
class HttpException extends RuntimeException
{
    private array $details;

    public function __construct(int $statusCode, string $message, array $details = [])
    {
        parent::__construct($message, $statusCode);
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->getCode() ?: 400;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
