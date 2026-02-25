<?php

declare(strict_types=1);

namespace Four\Http\Middleware;

trait SanitizesUrl
{
    private function sanitizeUrl(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        $sanitized = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'unknown');

        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }

        return $sanitized;
    }
}
