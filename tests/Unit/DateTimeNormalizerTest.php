<?php

namespace Tests\Unit;

use App\Services\SystemData\DateTimeNormalizer;
use Tests\TestCase;

class DateTimeNormalizerTest extends TestCase
{
    public function test_normalizes_iso8601_utc_timestamp_with_microseconds(): void
    {
        $input = '2026-08-25T05:11:52.351068Z';
        $normalized = DateTimeNormalizer::normalizeDateTime($input, 'Asia/Manila');

        // UTC 05:11:52 + 8 hours = 13:11:52, microseconds preserved
        $this->assertSame('2026-08-25 13:11:52.351068', $normalized);
    }

    public function test_normalizes_iso8601_timestamp_with_timezone_offset(): void
    {
        $input = '2026-08-25T13:11:52.500000+08:00';
        $normalized = DateTimeNormalizer::normalizeDateTime($input, 'Asia/Manila');

        $this->assertSame('2026-08-25 13:11:52.500000', $normalized);
    }

    public function test_normalizes_timestamp_without_microseconds(): void
    {
        $input = '2026-08-25 13:11:52';
        $normalized = DateTimeNormalizer::normalizeDateTime($input, 'Asia/Manila');

        $this->assertSame('2026-08-25 13:11:52', $normalized);
    }

    public function test_normalizes_iso8601_utc_without_microseconds(): void
    {
        $input = '2026-08-25T05:11:52Z';
        $normalized = DateTimeNormalizer::normalizeDateTime($input, 'Asia/Manila');

        $this->assertSame('2026-08-25 13:11:52', $normalized);
    }

    public function test_normalizes_date_field_to_yyyy_mm_dd(): void
    {
        $this->assertSame('2026-08-25', DateTimeNormalizer::normalizeDate('2026-08-25', 'Asia/Manila'));
        $this->assertSame('2026-08-25', DateTimeNormalizer::normalizeDate('2026-08-25 14:30:00', 'Asia/Manila'));
        $this->assertSame('2026-08-25', DateTimeNormalizer::normalizeDate('2026-08-25T05:11:52.351068Z', 'Asia/Manila'));
    }

    public function test_null_value_remains_null(): void
    {
        $this->assertNull(DateTimeNormalizer::normalizeDateTime(null));
        $this->assertNull(DateTimeNormalizer::normalizeDateTime(''));
        $this->assertNull(DateTimeNormalizer::normalizeDate(null));
        $this->assertNull(DateTimeNormalizer::normalizeDate(''));

        $this->assertTrue(DateTimeNormalizer::isValidDateTime(null));
        $this->assertTrue(DateTimeNormalizer::isValidDateTime(''));
        $this->assertTrue(DateTimeNormalizer::isValidDate(null));
        $this->assertTrue(DateTimeNormalizer::isValidDate(''));
    }

    public function test_rejects_invalid_datetime_values(): void
    {
        $this->assertFalse(DateTimeNormalizer::isValidDateTime('invalid-datetime'));
        $this->assertFalse(DateTimeNormalizer::isValidDateTime('2026-99-99 00:00:00'));
        $this->assertFalse(DateTimeNormalizer::isValidDateTime('not a date'));
    }

    public function test_rejects_invalid_date_values(): void
    {
        $this->assertFalse(DateTimeNormalizer::isValidDate('invalid-date'));
        $this->assertFalse(DateTimeNormalizer::isValidDate('2026-13-45'));
    }
}
