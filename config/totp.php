<?php
/*
 * Minimal TOTP (RFC 6238) implementation — compatible with
 * Google Authenticator, Microsoft Authenticator, Authy, etc.
 * No external libraries required.
 */

class TOTP {

    /* Generate a random Base32 secret (used once, at setup) */
    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /* Build the otpauth:// URI used to generate the QR code */
    public static function getProvisioningUri($secret, $accountName, $issuer = 'Klaso') {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /* Decode a Base32 string into raw binary */
    private static function base32Decode($b32) {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(rtrim($b32, '='));
        $binaryString = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos($map, $char);
            if ($pos === false) continue;
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = [];
        foreach (str_split($binaryString, 8) as $byte) {
            if (strlen($byte) == 8) {
                $bytes[] = chr(bindec($byte));
            }
        }
        return implode('', $bytes);
    }

    /* Generate the 6-digit code for a given secret at the current time */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        $code = $value % 1000000;
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /* Verify a submitted code, allowing +/- 1 time step for clock drift */
    public static function verifyCode($secret, $code, $window = 1) {
        $timeSlice = floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculated = self::getCode($secret, $timeSlice + $i);
            if (hash_equals($calculated, (string)$code)) {
                return true;
            }
        }
        return false;
    }

    /* Generate a set of one-time backup codes */
    public static function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}