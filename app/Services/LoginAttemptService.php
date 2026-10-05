<?php

namespace App\Services;

use App\Enums\NotificationType;
use Illuminate\Http\Request;
use App\Models\Claim;
use App\Models\IpLoginLock;
use App\Models\LoginAttempt;
use App\Models\Payment;
use App\Models\PaymentLink;
use Illuminate\Support\Facades\Log;

class LoginAttemptService
{
public function addFailedLoginAttempt(Request $request): void
    {
        try {
            $this->registerFailedAttemptForIp($request->ip());
        } catch (\Throwable $th) {
            Log::error('Failed to register IP lockout attempt', [
                'ip' => $request->ip(),
                'message' => $th->getMessage(),
            ]);
        }
        try {
            $claim = $this->resolveClaim($request);

            $assignedUser = $claim?->assignee?->first()?->toUser;

            $request->merge([
                'user_id' => $assignedUser?->id,
            ]);

            // لو مفيش claim اتربطت، برضه سجل محاولة فاشلة على مستوى الـ IP
            $this->logAttempt($request, $claim, false);


            if ($claim) {
                $notification = NotificationService::create([
                    'title_ar' => __('notification.failed_login_attempt', [], 'ar'),
                    'title_en' => __('notification.failed_login_attempt', [], 'en'),
                    'description_ar' => __('notification.failed_login_attempt_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                    'description_en' => __('notification.failed_login_attempt_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                    'regarding' => NotificationType::FAILD_CLAIM_SIGNIN->value,
                    'user_id' => $claim->assignee->to,
                    'to' => json_encode([$claim->assignee->to]),
                    'links' => json_encode([
                        [
                            'label_ar' => 'الذهاب للمطالبة',
                            'label_en' => 'go to Claim',
                            'url' => 'claims/'. $claim->id.'/login-attempt',
                        ],
                    ]),
                ]);
            }

        } catch (\Throwable $th) {
            Log::error('LoginAttemptService::addFailedLoginAttempt audit/notification failed', [
            'ip' => $request->ip(),
            'message' => $th->getMessage(),
            ]);
        }
    }

    public function addSucceededLoginAttempt(Request $request): void
    {
        try {
            $claim = $this->resolveClaim($request);

            $assignedUser = $claim?->assignee?->first()?->toUser;

            $request->merge([
                'user_id' => $assignedUser?->id,
            ]);

            $this->logAttempt($request, $claim, true);

            $this->clearIpLock($request->ip());


        } catch (\Throwable $th) {

        }
    }

    private function logAttempt(Request $request, ?Claim $claim, bool $isSuccessful): ?LoginAttempt
    {
        return LoginAttempt::create([
            'claim_id' => $claim?->id,
            'user_id' => $request->input('user_id'),
            'claim_number' => $request->input('claim_number', $claim?->claim_number),
            'phone_number' => $request->input('phone', $claim?->debtor_phone),
            'national_number' => $request->input('debtor_nid', $claim?->debtor_nid),
            'verification_code' => $request->verification_code,
            'ip_address' => $request->ip(),
            'is_successful' => $isSuccessful,
        ]);
    }

    // ============ IP-based rate limiting ============

    public function isBlockedByIp(string $ip): bool
    {
        return false; // skip ip-based rate limiting
        $lock = IpLoginLock::where('ip_address', $ip)->first();

        return (bool) ($lock && $lock->locked_until && $lock->locked_until->isFuture());
    }

    public function remainingLockSecondsByIp(string $ip): int
    {
        $lock = IpLoginLock::where('ip_address', $ip)->first();

        if (!$lock || !$lock->locked_until || $lock->locked_until->isPast()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($lock->locked_until, false));
    }

    private function registerFailedAttemptForIp(string $ip): void
    {
        $lock = IpLoginLock::firstOrCreate(['ip_address' => $ip]);

        // لو أصلاً متحظر، متزودش عليه تاني عشان الحظر ميتصعدش من غير داعي
        if ($lock->locked_until && $lock->locked_until->isFuture()) {
            return;
        }

        $lock->failed_attempts += 1;

        if ($lock->failed_attempts >= $this->maxAttemptsPerIp()) {
            $lock->block_count += 1;
            $lock->locked_until = now()->addMinutes($this->lockDurationMinutes($lock->block_count));
            $lock->failed_attempts = 0;
        }

        $lock->save();
    }

    private function clearIpLock(string $ip): void
    {
        IpLoginLock::where('ip_address', $ip)->delete();
    }

    private function maxAttemptsPerIp(): int
    {
        return config('login_attempts.login_max_attempts_per_ip'); // عدد المحاولات الغلط قبل ما يتحظر
    }

    private function lockDurationMinutes(int $blockCount): int
    {
        $durations = config('login_attempts.lock_durations_minutes');
        return $durations[$blockCount] ?? config('login_attempts.login_lock_duration_default_minutes');
    }

    private function resolveClaim(Request $request): ?Claim
    {
        if ($request->filled('claim_id')) {
            return Claim::find($request->claim_id);
        }

        if ($request->filled('verification_code')) {
            $payment = Payment::where('verification_code', $request->verification_code)->first();

            if ($payment) {
                return $payment->claim;
            }

            $paymentLink = PaymentLink::where('verification_code', $request->verification_code)->first();

            if ($paymentLink) {
                return $paymentLink->payment?->claim;
            }
        }


        // fallback to get the claim based on 
        $nid = $request->input('debtor_nid');
        $phone = $request->input('phone');
        $claimNumber = $request->input('claim_number');

        if ($nid || $phone || $claimNumber) {
            return Claim::where(function ($q) use ($nid, $phone, $claimNumber) {
                if ($nid) {
                    $q->orWhere('debtor_nid', $nid);
                }
                if ($phone) {
                    $q->orWhere('debtor_phone', $phone);
                }
                if ($claimNumber) {
                    $q->orWhere('claim_number', $claimNumber);
                }
            })->first();
        }

        return null;
    }


    public function checkIpLockout(string $ip): ?\Illuminate\Http\JsonResponse
    {
        if (! $this->isBlockedByIp($ip)) {
            return null;
        }

        $seconds = $this->remainingLockSecondsByIp($ip);

        return response()->json([
            'success' => false,
            'message' => "تم تجاوز عدد المحاولات المسموح بها، حاول مرة أخرى بعد {$seconds} ثانية",
        ], 429);
    }
}