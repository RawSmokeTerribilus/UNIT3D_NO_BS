<?php

declare(strict_types=1);

/**
 * NOBS — Nuclear Order Bit Syndicate
 *
 * Copyright (C) 2026 RawSmoke <https://nobs.rawsmoke.net>
 *
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * @project    NOBS — https://nobs.rawsmoke.net
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html  GNU AGPL v3.0
 */

namespace App\Services\Books\Support;

/**
 * ISBN parsing and normalisation.
 *
 * The books table is keyed by ISBN-13, so every identifier that reaches it
 * has to arrive in that shape. Google Books reports ISBN_10 alone for older
 * editions — the Kernighan C book from 1985, for one — and the conversion to
 * ISBN-13 is deterministic, so those editions are not lost: prefix 978, drop
 * the old check digit, recompute.
 */
final class Isbn
{
    /**
     * Strip formatting and uppercase the trailing X of an ISBN-10.
     */
    public static function clean(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        return strtoupper(preg_replace('/[^0-9Xx]/', '', $raw) ?? '');
    }

    /**
     * Normalise any ISBN form to a valid ISBN-13, or '' when it is neither a
     * valid ISBN-10 nor a valid ISBN-13.
     */
    public static function toIsbn13(?string $raw): string
    {
        $s = self::clean($raw);

        if (\strlen($s) === 13) {
            return self::isValidIsbn13($s) ? $s : '';
        }

        if (\strlen($s) === 10) {
            return self::isValidIsbn10($s) ? self::fromIsbn10($s) : '';
        }

        return '';
    }

    /**
     * Convert a valid ISBN-10 to its ISBN-13 form. The caller is expected to
     * have validated it.
     */
    public static function fromIsbn10(string $isbn10): string
    {
        $body = '978'.substr(self::clean($isbn10), 0, 9);

        return $body.self::checkDigit13($body);
    }

    public static function isValidIsbn13(string $s): bool
    {
        $s = self::clean($s);

        if (\strlen($s) !== 13 || preg_match('/^\d{13}$/', $s) !== 1) {
            return false;
        }

        return self::checkDigit13(substr($s, 0, 12)) === $s[12];
    }

    public static function isValidIsbn10(string $s): bool
    {
        $s = self::clean($s);

        if (\strlen($s) !== 10 || preg_match('/^\d{9}[0-9X]$/', $s) !== 1) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $digit = $s[$i] === 'X' ? 10 : (int) $s[$i];
            $sum += $digit * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    /**
     * The ISBN-13 check digit for a 12-digit body: digits weighted 1,3,1,3….
     */
    private static function checkDigit13(string $body12): string
    {
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $sum % 10) % 10);
    }
}
