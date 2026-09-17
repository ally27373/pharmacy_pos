<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}

// Canonical server timezone: timestamps are stored/handled in UTC.
// Display converts UTC -> Asia/Manila via toPhTime() (display only,
// storage is never shifted).
date_default_timezone_set('UTC');

if (!function_exists('toPhTime')) {

    /**
     * Format a UTC datetime string for Philippine display (UTC+8).
     */
    function toPhTime(?string $utcDateTime, string $format = 'M d, Y h:i A'): string
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return 'N/A';
        }

        try {
            $dt = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
            return $dt->format($format);
        } catch (Throwable $e) {
            return 'N/A';
        }
    }

}