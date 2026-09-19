<?php

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asigna un correlation id por request: viaja a cada SP (@CorrelationId → bitácora),
 * al contexto de logs de Laravel y a la cabecera X-Correlation-ID de la respuesta.
 */
final class AssignCorrelationId
{
    public function __construct(private readonly RequestContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->initializeFromRequest($request);
        Log::withContext(['correlation_id' => $this->context->correlationId()]);

        $response = $next($request);
        $response->headers->set(RequestContext::HEADER, $this->context->correlationId());

        return $response;
    }
}
