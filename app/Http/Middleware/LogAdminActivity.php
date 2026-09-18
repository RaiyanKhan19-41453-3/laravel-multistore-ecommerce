<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    /**
     * Field fragments that must never land in the audit trail.
     */
    private const SENSITIVE = [
        'password',
        'token',
        'secret',
        'private_key',
        'certificate',
        'auth_token',
        'api_token',
        'api_key',
        'card_number',
        'cvv',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user || $request->isMethodSafe() || ! $request->is('admin/*')) {
            return $response;
        }

        try {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => (string) ($request->route()?->getName() ?? 'admin.unknown'),
                'method' => $request->getMethod(),
                'path' => substr($request->path(), 0, 500),
                'ip' => $request->ip(),
                'status' => $response->getStatusCode(),
                'changes' => $this->sanitized($request),
            ]);
        } catch (\Throwable) {
            // Auditing must never break the admin action itself.
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitized(Request $request): array
    {
        $input = $request->except(array_keys($request->allFiles()));

        return $this->sanitizeValue($input);
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeValue(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            $lower = strtolower((string) $key);

            foreach (self::SENSITIVE as $fragment) {
                if (str_contains($lower, $fragment)) {
                    $clean[$key] = '[redacted]';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeValue($value);
            } elseif (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 500);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $encoded = json_encode($value);
                $clean[$key] = mb_substr(is_string($encoded) ? $encoded : '[unserializable]', 0, 500);
            }
        }

        return $clean;
    }
}
