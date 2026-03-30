<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class MfaService
{
    private const TIME_STEP = 30;
    private const CODE_LENGTH = 6;

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function enable(User $user): string
    {
        $secret = $this->generateSecret();
        $user->update([
            'mfa_secret' => $secret,
            'mfa_enabled' => true,
        ]);

        return $secret;
    }

    public function disable(User $user): void
    {
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
        ]);
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (!$user->mfa_enabled || empty($user->mfa_secret)) {
            return false;
        }

        $code = trim($code);
        if (!ctype_digit($code) || strlen($code) !== self::CODE_LENGTH) {
            return false;
        }

        $secret = $this->base32Decode($user->mfa_secret);
        if (!$secret) {
            return false;
        }

        $timeSlice = (int) floor(time() / self::TIME_STEP);

        // allow one step drift on either side
        foreach ([-1, 0, 1] as $offset) {
            $totp = $this->calcTotp($secret, $timeSlice + $offset);
            if (hash_equals($totp, $code)) {
                return true;
            }
        }

        return false;
    }

    private function calcTotp(string $secret, int $timeSlice): string
    {
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);
        $value = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;
        $modulo = 10 ** self::CODE_LENGTH;

        return str_pad((string) ($value % $modulo), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $encoded = '';

        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return trim(chunk_split($encoded, 4, ' '));
    }

    private function base32Decode(string $data): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(str_replace([' ', '='], '', $data));
        $bits = '';
        $decoded = '';

        foreach (str_split($clean) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                return null;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}

