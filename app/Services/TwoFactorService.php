<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Services\AuditService;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Email-based one-time-code engine shared by the settings 2FA flow and the
 * login challenge. Codes are hashed at rest and expire after 10 minutes.
 */
class TwoFactorService
{
    public const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate, store (hashed) and email a fresh 6-digit verification code.
     */
    public function issueCode(User $user, string $context = 'login'): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $settings = $user->settingsOrCreate();
        $settings->forceFill([
            'two_factor_code'            => Hash::make($code),
            'two_factor_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'two_factor_code_attempts'   => 0,
            'two_factor_last_sent_at'    => now(),
        ])->save();

        $this->sendCodeEmail($user, $code, $context);

        return $code;
    }

    public function assertResendAllowed(User $user): void
    {
        $lastSentAt = $user->settings?->two_factor_last_sent_at;
        if ($lastSentAt && $lastSentAt->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            throw ValidationException::withMessages([
                'message' => 'Please wait before requesting another verification code.',
            ]);
        }
    }

    /**
     * Verify a submitted code against the stored hash; clears it on success.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $settings = $user->settings;

        if (!$settings || !$settings->two_factor_code || !$settings->two_factor_code_expires_at) {
            return false;
        }

        if ((int) $settings->two_factor_code_attempts >= self::MAX_ATTEMPTS) {
            $this->clearCode($settings);
            return false;
        }

        if (now()->gt($settings->two_factor_code_expires_at)) {
            $this->clearCode($settings);
            return false;
        }

        if (!Hash::check($code, $settings->two_factor_code)) {
            $settings->increment('two_factor_code_attempts');
            if ((int) $settings->fresh()->two_factor_code_attempts >= self::MAX_ATTEMPTS) {
                $this->clearCode($settings->fresh());
            }
            return false;
        }

        $this->clearCode($settings);
        return true;
    }

    /**
     * Mark 2FA as enabled/disabled, clearing any pending challenge code.
     */
    public function setEnabled(User $user, bool $enabled, ?string $channel = null): void
    {
        AuditService::log(
            $user,
            $enabled ? AuditAction::TwoFactorEnabled : AuditAction::TwoFactorDisabled,
            'users',
            $user->id,
            [],
            ['two_factor_enabled' => $enabled, 'two_factor_channel' => $channel],
        );

        $settings = $user->settingsOrCreate();
        $settings->forceFill([
            'two_factor_enabled'         => $enabled,
            'two_factor_channel'         => $enabled ? ($channel ?? 'email') : null,
            'two_factor_code'            => null,
            'two_factor_code_expires_at' => null,
            'two_factor_code_attempts'   => 0,
            'two_factor_last_sent_at'    => null,
        ])->save();
    }

    private function clearCode(UserSetting $settings): void
    {
        $settings->forceFill([
            'two_factor_code'            => null,
            'two_factor_code_expires_at' => null,
        ])->save();
    }

    private function sendCodeEmail(User $user, string $code, string $context): void
    {
        $subject = $context === 'login'
            ? '[BankVision] Your sign-in verification code'
            : '[BankVision] Your two-factor verification code';

        $body = "Hello {$user->name},\n\n"
            . "Your BankVision verification code is: {$code}\n\n"
            . 'It expires in ' . self::CODE_TTL_MINUTES . " minutes. If you did not request it, please contact your administrator.\n\n"
            . "- BankVision Security";

        try {
            Mail::raw($body, function ($mail) use ($user, $subject) {
                $mail->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            // Do not place authentication credentials in application logs.
            report($e);
        }
    }
}
