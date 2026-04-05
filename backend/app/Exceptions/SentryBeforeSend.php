<?php

namespace App\Exceptions;

use Sentry\Event;
use Sentry\EventHint;

class SentryBeforeSend
{
    /**
     * Sensitive keys to redact from request data and breadcrumbs.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'authorization',
        'cookie',
        'token',
        'api_key',
        'secret',
        'firebase_private_key',
        'private_key',
        'credit_card',
    ];

    public static function handle(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if (! empty($request)) {
            // Scrub sensitive headers
            if (isset($request['headers'])) {
                foreach ($request['headers'] as $key => $value) {
                    if (self::isSensitive($key)) {
                        $request['headers'][$key] = '[Filtered]';
                    }
                }
            }

            // Scrub sensitive body data
            if (isset($request['data']) && is_array($request['data'])) {
                $request['data'] = self::scrubArray($request['data']);
            }

            $event->setRequest($request);
        }

        return $event;
    }

    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (self::isSensitive($key)) {
                $data[$key] = '[Filtered]';
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            }
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
