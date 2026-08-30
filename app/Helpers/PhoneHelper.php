<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalize a Bangladesh phone number to local format (01XXXXXXXXX).
     *
     * Handles: 01712345678, +8801712345678, 8801712345678, 01712345678
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $phone = preg_replace('/[\s\-\(\)]/', '', trim($phone));

        if (preg_match('/^(\+880|880)(1[3-9]\d{8})$/', $phone, $matches)) {
            return '0'.$matches[2];
        }

        if (preg_match('/^(01[3-9]\d{8})$/', $phone)) {
            return $phone;
        }

        return $phone;
    }
}
