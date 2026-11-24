<?php

declare(strict_types=1);

namespace ContactForm\Admin;

use ContactForm\Security\CsrfGuard;
use ContactForm\Security\FieldCipher;
use ContactForm\Security\PasswordHasher;
use ContactForm\Security\RateLimiter;
use ContactForm\Security\Session\SessionStore;
use ContactForm\Security\TotpVerifier;
use ContactForm\Support\Clock;

final readonly class AdminAuthenticator
{
    private const int MAX_FAILURES = 5;
    private const int LOCK_SECONDS = 900;
    private const int PENDING_TTL_SECONDS = 300;
    private const int IDLE_TIMEOUT_SECONDS = 900;
    private const int ABSOLUTE_TIMEOUT_SECONDS = 28800;
    private const int LOGIN_LIMIT = 10;
    private const int LOGIN_WINDOW_SECONDS = 900;
    private const int SECOND_FACTOR_LIMIT = 5;
    private const int SECOND_FACTOR_WINDOW_SECONDS = 300;
    private const string PENDING_ID = 'auth.pending_id';
    private const string PENDING_AT = 'auth.pending_at';
    private const string USER_ID = 'auth.user_id';
    private const string AUTHENTICATED_AT = 'auth.authenticated_at';
    private const string LAST_SEEN_AT = 'auth.last_seen_at';

    public function __construct(
        private AdminUserRepository $users,
        private PasswordHasher $passwords,
        private TotpVerifier $totp,
        private FieldCipher $cipher,
        private RateLimiter $rateLimiter,
        private SessionStore $session,
        private CsrfGuard $csrf,
        private AuditLog $audit,
        private Clock $clock,
    ) {
    }

    public static function totpContext(string $username): string
    {
        return 'admin_users:' . strtolower($username) . ':totp';
    }

    public function attemptPassword(string $username, string $password, string $clientAddress): AuthOutcome
    {
        $normalizedUsername = strtolower(trim($username));
        if (
            !$this->rateLimiter->allow('login-address', $clientAddress, self::LOGIN_LIMIT, self::LOGIN_WINDOW_SECONDS)
            || !$this->rateLimiter->allow('login-account', $normalizedUsername, self::LOGIN_LIMIT, self::LOGIN_WINDOW_SECONDS)
        ) {
            return AuthOutcome::RateLimited;
        }
        $user = $this->users->findByUsername($normalizedUsername);
        if ($user === null) {
            $this->passwords->burnVerification($password);
            $this->audit->record('login_password_failed', null, $clientAddress);

            return AuthOutcome::Rejected;
        }
        $passwordMatches = $this->passwords->verify($password, $user->passwordHash);
        if (!$passwordMatches || $this->isLocked($user)) {
            if (!$this->isLocked($user)) {
                $this->users->registerFailure($user->id, self::MAX_FAILURES, self::LOCK_SECONDS);
            }
            $this->audit->record('login_password_failed', $user->id, $clientAddress);

            return AuthOutcome::Rejected;
        }
        if ($this->passwords->needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, $this->passwords->hash($password));
        }
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->set(self::PENDING_ID, $user->id);
        $this->session->set(self::PENDING_AT, $this->clock->now());

        return AuthOutcome::Succeeded;
    }

    public function hasPendingSecondFactor(): bool
    {
        $pendingAt = $this->session->get(self::PENDING_AT);

        return is_int($this->session->get(self::PENDING_ID))
            && is_int($pendingAt)
            && $this->clock->now() - $pendingAt <= self::PENDING_TTL_SECONDS;
    }

    public function attemptSecondFactor(string $code, string $clientAddress): AuthOutcome
    {
        if (!$this->hasPendingSecondFactor()) {
            $this->clearPending();

            return AuthOutcome::Expired;
        }
        $userId = (int) $this->session->get(self::PENDING_ID);
        if (!$this->rateLimiter->allow('second-factor', (string) $userId, self::SECOND_FACTOR_LIMIT, self::SECOND_FACTOR_WINDOW_SECONDS)) {
            return AuthOutcome::RateLimited;
        }
        $user = $this->users->findById($userId);
        if ($user === null || $this->isLocked($user)) {
            $this->clearPending();

            return AuthOutcome::Expired;
        }
        $usedRecoveryCode = false;
        $verified = $this->verifyTotp($user, $code);
        if (!$verified && RecoveryCodes::normalize($code) !== null) {
            $usedRecoveryCode = $this->users->consumeRecoveryCode($user->id, RecoveryCodes::hash($code));
            $verified = $usedRecoveryCode;
        }
        if (!$verified) {
            $this->users->registerFailure($user->id, self::MAX_FAILURES, self::LOCK_SECONDS);
            $this->audit->record('login_second_factor_failed', $user->id, $clientAddress);

            return AuthOutcome::Rejected;
        }
        $this->users->clearFailures($user->id);
        $this->clearPending();
        $this->session->regenerate();
        $this->csrf->rotate();
        $now = $this->clock->now();
        $this->session->set(self::USER_ID, $user->id);
        $this->session->set(self::AUTHENTICATED_AT, $now);
        $this->session->set(self::LAST_SEEN_AT, $now);
        if ($usedRecoveryCode) {
            $this->audit->record('recovery_code_used', $user->id, $clientAddress);
        }
        $this->audit->record('login_succeeded', $user->id, $clientAddress);

        return AuthOutcome::Succeeded;
    }

    public function currentUserId(): ?int
    {
        $userId = $this->session->get(self::USER_ID);
        $authenticatedAt = $this->session->get(self::AUTHENTICATED_AT);
        $lastSeenAt = $this->session->get(self::LAST_SEEN_AT);
        if (!is_int($userId) || !is_int($authenticatedAt) || !is_int($lastSeenAt)) {
            return null;
        }
        $now = $this->clock->now();
        if ($now - $authenticatedAt > self::ABSOLUTE_TIMEOUT_SECONDS || $now - $lastSeenAt > self::IDLE_TIMEOUT_SECONDS) {
            $this->clearAuthenticated();

            return null;
        }
        if ($this->users->findById($userId) === null) {
            $this->clearAuthenticated();

            return null;
        }
        $this->session->set(self::LAST_SEEN_AT, $now);

        return $userId;
    }

    public function logout(string $clientAddress): void
    {
        $userId = $this->currentUserId();
        if ($userId !== null) {
            $this->audit->record('logout', $userId, $clientAddress);
        }
        $this->session->destroy();
    }

    private function verifyTotp(AdminUser $user, string $code): bool
    {
        $secret = $this->cipher->decrypt($user->encryptedTotpSecret, self::totpContext($user->username));
        $step = $this->totp->matchingStep($secret, trim($code), $user->totpLastStep);

        return $step !== null && $this->users->advanceTotpStep($user->id, $step);
    }

    private function isLocked(AdminUser $user): bool
    {
        return $user->lockedUntil > $this->clock->now();
    }

    private function clearPending(): void
    {
        $this->session->remove(self::PENDING_ID);
        $this->session->remove(self::PENDING_AT);
    }

    private function clearAuthenticated(): void
    {
        $this->session->remove(self::USER_ID);
        $this->session->remove(self::AUTHENTICATED_AT);
        $this->session->remove(self::LAST_SEEN_AT);
    }
}
