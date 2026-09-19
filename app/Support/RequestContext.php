<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Datos de contexto que viajan a la bitácora SQL: IP, User-Agent y correlation id.
 * Vive una vez por request (binding scoped).
 */
final class RequestContext
{
    public const HEADER = 'X-Correlation-ID';

    private ?string $correlationId = null;

    private ?string $ip = null;

    private ?string $userAgent = null;

    public function initializeFromRequest(Request $request): void
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $this->correlationId = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();
        $this->ip = $request->ip();
        $this->userAgent = Str::limit((string) $request->userAgent(), 297, '...');
    }

    public function initializeForConsole(string $source): void
    {
        $this->correlationId = 'cli-'.Str::uuid();
        $this->ip = null;
        $this->userAgent = 'artisan '.$source;
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= (string) Str::uuid();
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    /** @return array{Ip: ?string, UserAgent: ?string, CorrelationId: string} */
    public function procedureParameters(): array
    {
        return [
            'Ip' => $this->ip,
            'UserAgent' => $this->userAgent,
            'CorrelationId' => $this->correlationId(),
        ];
    }
}
