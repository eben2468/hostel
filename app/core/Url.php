<?php
namespace App\Core;

/** URL safety helpers for redirects. */
class Url
{
    /**
     * The page to send someone back to, but only if it is our own.
     *
     * `Referer` is set by the browser and can be pointed anywhere, so following
     * it verbatim turns any "go back" into an open redirect: a link into the
     * app could bounce the victim to a look-alike sign-in page while the URL
     * they clicked was genuinely ours. Anything that is not a same-origin
     * application path is discarded in favour of $fallback.
     *
     * @param string $fallback application-relative path used when the referer
     *                         is missing, foreign, or malformed
     * @return string an application-relative path (never an absolute URL)
     */
    public static function safeReferer(string $fallback = '/'): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        if ($parts === false) {
            return $fallback;
        }

        // An absolute URL is only acceptable when it points back at this host.
        if (isset($parts['host'])) {
            $host = strtolower($parts['host']);
            $self = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            // Compare without the port so http://site and http://site:443 match.
            if ($host !== strtolower(explode(':', $self)[0]) && $host . ':' . ($parts['port'] ?? '') !== $self) {
                return $fallback;
            }
        }

        $path = (string) ($parts['path'] ?? '');
        // "//evil.com" is protocol-relative: the browser reads it as a host.
        if ($path === '' || str_starts_with($path, '//')) {
            return $fallback;
        }

        // Re-express as a path relative to the application base. A same-host
        // path that is NOT inside this application (another app sharing the
        // domain, as on shared hosting) is rejected rather than rewritten:
        // callers prefix BASE_URL, so passing it through would build nonsense.
        $base = rtrim((string) (parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');
        if ($base !== '') {
            if (!str_starts_with($path, $base . '/') && $path !== $base) {
                return $fallback;
            }
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }
        return $path;
    }
}
