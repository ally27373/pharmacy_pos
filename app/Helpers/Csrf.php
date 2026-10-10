<?php

declare(strict_types=1);

/**
 * =====================================================
 * CSRF protection
 * =====================================================
 *
 * Role checks answer "who is asking". They say nothing about "did this
 * browser intend to ask" -- an admin's signed-in cookie is attached by the
 * browser to any cross-site form post, so without this a page anywhere on
 * the internet could archive products or create sales as that admin.
 *
 * The token is minted once per session and must come back on every
 * state-changing request, either as a hidden field (plain HTML forms) or
 * the X-CSRF-Token header (fetch). The header is preferred for XHR because
 * it needs no change to how payloads are built.
 *
 * Deliberately a plain global class, not a namespaced one: the ajax
 * endpoints reach middleware through an explicit require rather than
 * composer autoload, and this has to work identically on a deploy where
 * vendor/ has not been installed.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';
    private const FIELD_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * Current session token, minting one on first use.
     */
    public static function token(): string
    {
        self::startSession();

        if (
            !isset($_SESSION[self::SESSION_KEY])
            || !is_string($_SESSION[self::SESSION_KEY])
            || $_SESSION[self::SESSION_KEY] === ''
        ) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Hidden input for plain HTML form posts.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Token supplied with this request, if any.
     */
    public static function fromRequest(): ?string
    {
        if (isset($_POST[self::FIELD_NAME]) && is_string($_POST[self::FIELD_NAME])) {
            return $_POST[self::FIELD_NAME];
        }

        $headers = self::requestHeaders();

        foreach ($headers as $name => $value) {
            if (strcasecmp($name, self::HEADER_NAME) === 0) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return null;
    }

    /**
     * Constant-time comparison against the session token.
     */
    public static function isValid(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        self::startSession();

        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        // hash_equals() so a rejected token leaks nothing about the real one
        // through response timing.
        return hash_equals($expected, $candidate);
    }

    /**
     * Guard for state-changing requests. Answer 403 rather than silently
     * dropping the write: a caller that is not sending a token is almost
     * always a caller that needs to be told.
     *
     * Call this AFTER the role guard, so an anonymous request is reported as
     * an authentication failure instead of a CSRF failure.
     */
    public static function requireValid(bool $jsonResponse = true): void
    {
        if (self::isValid(self::fromRequest())) {
            return;
        }

        $message = 'Your session token is missing or expired. Reload the page and try again.';

        if ($jsonResponse) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode([
                'status' => 'error',
                'message' => $message
            ]);

            exit;
        }

        if (!headers_sent()) {
            http_response_code(403);
        }

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Session expired</title></head><body>'
            . '<h2>Session expired</h2><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p><p><a href="/app/auth/login.php">Sign in again</a></p>'
            . '</body></html>';

        exit;
    }

    /**
     * Discard the token. Used when the privilege level changes -- signing in
     * or out -- so a token minted for one account cannot be carried into
     * another.
     */
    public static function reset(): void
    {
        self::startSession();

        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * @return array<string, string|string[]>
     */
    private static function requestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            $name = str_replace(
                ' ',
                '-',
                ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
            );

            $headers[$name] = $value;
        }

        return $headers;
    }
}