<?php

declare(strict_types=1);

namespace ContactForm;

use ContactForm\Admin\AdminAuthenticator;
use ContactForm\Admin\AdminUserRepository;
use ContactForm\Admin\AuditLog;
use ContactForm\Config\AppConfig;
use ContactForm\Contact\ContactService;
use ContactForm\Contact\ContactValidator;
use ContactForm\Contact\SubmissionRepository;
use ContactForm\Http\Kernel;
use ContactForm\Mail\DisabledSubmissionMailer;
use ContactForm\Mail\SmtpSubmissionMailer;
use ContactForm\Mail\SubmissionMailer;
use ContactForm\Persistence\Database;
use ContactForm\Security\CsrfGuard;
use ContactForm\Security\FieldCipher;
use ContactForm\Security\Keyring;
use ContactForm\Security\PasswordHasher;
use ContactForm\Security\RateLimiter;
use ContactForm\Security\Session\NativeSessionStore;
use ContactForm\Security\Session\SessionStore;
use ContactForm\Security\TotpVerifier;
use ContactForm\Support\Clock;
use ContactForm\Support\SystemClock;
use ContactForm\View\TemplateRenderer;
use PDO;

final readonly class Application
{
    public Kernel $kernel;
    public PDO $database;
    public FieldCipher $cipher;
    public PasswordHasher $passwords;
    public TotpVerifier $totp;
    public AdminUserRepository $adminUsers;
    public SubmissionRepository $submissions;
    public RateLimiter $rateLimiter;
    public AuditLog $audit;

    public function __construct(
        public AppConfig $config,
        public string $projectRoot,
        ?SessionStore $session = null,
        ?Clock $clock = null,
        ?SubmissionMailer $mailer = null,
    ) {
        $clock ??= new SystemClock();
        $session ??= new NativeSessionStore($config->secureCookies);
        $mailer ??= $config->mailEnabled ? new SmtpSubmissionMailer($config) : new DisabledSubmissionMailer();
        $keyring = Keyring::parse($config->keyring, $config->activeKeyId);
        $pseudonymizationKey = $keyring->pseudonymizationKey();

        $this->database = Database::connect($config->databasePath);
        $this->cipher = new FieldCipher($keyring);
        $this->passwords = new PasswordHasher($config->argonMemoryKib, $config->argonTimeCost, $config->argonThreads);
        $this->totp = new TotpVerifier($clock);
        $this->adminUsers = new AdminUserRepository($this->database, $clock);
        $this->submissions = new SubmissionRepository($this->database, $this->cipher, $clock);
        $this->rateLimiter = new RateLimiter($this->database, $clock, $pseudonymizationKey);
        $this->audit = new AuditLog($this->database, $clock, $pseudonymizationKey);
        $csrf = new CsrfGuard($session);
        $authenticator = new AdminAuthenticator(
            $this->adminUsers,
            $this->passwords,
            $this->totp,
            $this->cipher,
            $this->rateLimiter,
            $session,
            $csrf,
            $this->audit,
            $clock,
        );
        $this->kernel = new Kernel(
            $session,
            $csrf,
            $this->rateLimiter,
            new ContactValidator(),
            new ContactService($this->submissions, $mailer),
            $this->submissions,
            $authenticator,
            $this->audit,
            new TemplateRenderer($projectRoot . '/frontend/templates'),
            $config->secureCookies,
        );
    }

    public function migrate(): void
    {
        Database::migrate($this->database, $this->projectRoot . '/db/schema.sql');
    }
}
