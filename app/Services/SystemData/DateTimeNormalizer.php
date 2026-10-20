<?php

namespace App\Services\SystemData;

use Carbon\Carbon;

/**
 * Reusable normalization and validation service for date and datetime fields
 * processed during System Data export and import.
 */
class DateTimeNormalizer
{
    /**
     * Resolve target application timezone safely.
     */
    public static function getAppTimezone(?string $customTimezone = null): string
    {
        if ($customTimezone !== null && $customTimezone !== '') {
            return $customTimezone;
        }

        try {
            return config('app.timezone', 'Asia/Manila') ?: 'Asia/Manila';
        } catch (\Throwable) {
            return 'Asia/Manila';
        }
    }

    /**
     * Normalize an input timestamp/datetime string into a database-compatible DATETIME string.
     *
     * Preserves:
     * - Date and time
     * - Microseconds where present and non-zero
     * - Correct instant/timezone semantics (converts explicit UTC 'Z' or timezone offsets to application timezone)
     *
     * Accepts:
     * - ISO-8601 UTC (e.g. '2026-08-25T05:11:52.351068Z')
     * - ISO-8601 with offset (e.g. '2026-08-25T13:11:52+08:00')
     * - Standard SQL datetime (e.g. '2026-08-25 13:11:52')
     * - Standard SQL datetime with microseconds (e.g. '2026-08-25 13:11:52.351068')
     *
     * @throws \InvalidArgumentException if the input string cannot be parsed as a valid datetime
     */
    public static function normalizeDateTime(?string $value, ?string $appTimezone = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (!self::isValidDateTime($trimmed)) {
            throw new \InvalidArgumentException("Invalid datetime value: '{$value}'");
        }

        try {
            $carbon = @Carbon::parse($trimmed);
            $appTz = self::getAppTimezone($appTimezone);
            $carbon = $carbon->setTimezone($appTz);

            return $carbon->micro > 0
                ? $carbon->format('Y-m-d H:i:s.u')
                : $carbon->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Invalid datetime value: '{$value}'", 0, $e);
        }
    }

    /**
     * Normalize an input date string into a database-compatible DATE (YYYY-MM-DD) string.
     *
     * If the input is an ISO-8601 timestamp with an offset/UTC, it converts to application timezone
     * before extracting the calendar date.
     *
     * @throws \InvalidArgumentException if the input string cannot be parsed as a valid date
     */
    public static function normalizeDate(?string $value, ?string $appTimezone = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (!self::isValidDate($trimmed)) {
            throw new \InvalidArgumentException("Invalid date value: '{$value}'");
        }

        try {
            $carbon = @Carbon::parse($trimmed);
            $appTz = self::getAppTimezone($appTimezone);
            $carbon = $carbon->setTimezone($appTz);

            return $carbon->format('Y-m-d');
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Invalid date value: '{$value}'");
        }
    }

    /**
     * Check if a value represents a valid datetime string or null.
     */
    public static function isValidDateTime(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
            return false;
        }

        try {
            $parsed = @Carbon::parse($trimmed);
            $errors = \DateTime::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return false;
            }
            return $parsed !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a value represents a valid date string or null.
     */
    public static function isValidDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
            return false;
        }

        try {
            $parsed = @Carbon::parse($trimmed);
            $errors = \DateTime::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return false;
            }
            return $parsed !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
