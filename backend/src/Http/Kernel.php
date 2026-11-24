<?php

declare(strict_types=1);

namespace ContactForm\Http;

use ContactForm\Admin\AdminAuthenticator;
use ContactForm\Admin\AuditLog;
use ContactForm\Admin\AuthOutcome;
use ContactForm\Contact\ContactService;
use ContactForm\Contact\ContactValidator;
use ContactForm\Contact\SubmissionRepository;
use ContactForm\Security\CsrfGuard;
use ContactForm\Security\RateLimiter;
use ContactForm\Security\Session\SessionStore;
use ContactForm\View\TemplateRenderer;
use Throwable;

final readonly class Kernel
{
    private const int MAX_BODY_BYTES = 65536;
    private const int CONTACT_LIMIT = 10;
    private const int CONTACT_WINDOW_SECONDS = 600;
    private const int ADMIN_PAGE_SIZE = 25;
    private const string HONEYPOT_FIELD = 'website';
    private const string SUBMISSION_ROUTE = '#\A/admin/submissions/([a-f0-9]{32})\z#';

    public function __construct(
        private SessionStore $session,
        private CsrfGuard $csrf,
        private RateLimiter $rateLimiter,
        private ContactValidator $validator,
        private ContactService $contact,
        private SubmissionRepository $submissions,
        private AdminAuthenticator $authenticator,
        private AuditLog $audit,
        private TemplateRenderer $view,
        private bool $secureTransport,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $exception) {
            error_log('Unhandled request failure: ' . $exception::class);
            $response = $this->error(500, 'Something went wrong', 'The request could not be completed.');
        }

        return $response->withHeaders($this->securityHeaders());
    }

    private function dispatch(Request $request): Response
    {
        if ($request->contentLength > self::MAX_BODY_BYTES) {
            return $this->error(413, 'Request too large', 'The submitted data is too large.');
        }
        $this->session->start();
        if ($request->method === 'POST' && !$this->csrf->isValid($request->post['csrf_token'] ?? null)) {
            return $this->error(403, 'Request rejected', 'Your session expired. Reload the page and try again.');
        }
        $route = $request->method . ' ' . $request->path;

        return match ($route) {
            'GET /' => $this->showContactForm($request),
            'POST /' => $this->submitContactForm($request),
            'GET /admin/login' => $this->showLogin(),
            'POST /admin/login' => $this->submitLogin($request),
            'GET /admin/two-factor' => $this->showSecondFactor(),
            'POST /admin/two-factor' => $this->submitSecondFactor($request),
            'POST /admin/logout' => $this->logout($request),
            'GET /admin' => $this->listSubmissions($request),
            default => $this->routeDynamic($request),
        };
    }

    private function routeDynamic(Request $request): Response
    {
        if (preg_match(self::SUBMISSION_ROUTE, $request->path, $matches) === 1) {
            return $request->method === 'GET'
                ? $this->showSubmission($request, $matches[1])
                : $this->error(405, 'Method not allowed', 'This method is not supported here.')->withHeaders(['Allow' => 'GET']);
        }

        return $this->error(404, 'Page not found', 'The requested page does not exist.');
    }

    private function showContactForm(Request $request): Response
    {
        return $this->contactPage(['sent' => ($request->query['sent'] ?? null) === '1']);
    }

    private function submitContactForm(Request $request): Response
    {
        if ($request->postString(self::HONEYPOT_FIELD) !== '') {
            return Response::redirect('/?sent=1');
        }
        if (!$this->rateLimiter->allow('contact', $request->clientAddress, self::CONTACT_LIMIT, self::CONTACT_WINDOW_SECONDS)) {
            return $this->error(429, 'Too many requests', 'Please wait a few minutes and try again.')
                ->withHeaders(['Retry-After' => (string) self::CONTACT_WINDOW_SECONDS]);
        }
        $result = $this->validator->validate($request->post);
        if ($result->submission === null) {
            return $this->contactPage(['errors' => $result->errors, 'values' => $result->values], 422);
        }
        $this->contact->accept($result->submission);

        return Response::redirect('/?sent=1');
    }

    private function contactPage(array $variables, int $status = 200): Response
    {
        $defaults = ['sent' => false, 'errors' => [], 'values' => [], 'csrfToken' => $this->csrf->token(), 'honeypotField' => self::HONEYPOT_FIELD];

        return Response::html($this->view->page('contact/form', 'Contact', $variables + $defaults), $status);
    }

    private function showLogin(string $error = '', int $status = 200): Response
    {
        if ($this->authenticator->currentUserId() !== null) {
            return Response::redirect('/admin');
        }

        return Response::html($this->view->page('admin/login', 'Admin sign in', ['error' => $error, 'csrfToken' => $this->csrf->token()]), $status);
    }

    private function submitLogin(Request $request): Response
    {
        $outcome = $this->authenticator->attemptPassword(
            $request->postString('username'),
            $request->postString('password'),
            $request->clientAddress,
        );

        return match ($outcome) {
            AuthOutcome::Succeeded => Response::redirect('/admin/two-factor'),
            AuthOutcome::RateLimited => $this->showLogin('Too many attempts. Try again later.', 429),
            default => $this->showLogin('Invalid credentials.', 401),
        };
    }

    private function showSecondFactor(string $error = '', int $status = 200): Response
    {
        if (!$this->authenticator->hasPendingSecondFactor()) {
            return Response::redirect('/admin/login');
        }

        return Response::html($this->view->page('admin/two-factor', 'Two-factor authentication', ['error' => $error, 'csrfToken' => $this->csrf->token()]), $status);
    }

    private function submitSecondFactor(Request $request): Response
    {
        $outcome = $this->authenticator->attemptSecondFactor($request->postString('code'), $request->clientAddress);

        return match ($outcome) {
            AuthOutcome::Succeeded => Response::redirect('/admin'),
            AuthOutcome::Expired => Response::redirect('/admin/login'),
            AuthOutcome::RateLimited => $this->showSecondFactor('Too many attempts. Try again later.', 429),
            AuthOutcome::Rejected => $this->showSecondFactor('Invalid code.', 401),
        };
    }

    private function logout(Request $request): Response
    {
        $this->authenticator->logout($request->clientAddress);

        return Response::redirect('/admin/login');
    }

    private function listSubmissions(Request $request): Response
    {
        if ($this->authenticator->currentUserId() === null) {
            return Response::redirect('/admin/login');
        }
        $total = $this->submissions->count();
        $lastPage = max(1, (int) ceil($total / self::ADMIN_PAGE_SIZE));
        $requested = filter_var($request->query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
        $page = max(1, min((int) $requested, $lastPage));

        return Response::html($this->view->page('admin/submissions', 'Submissions', [
            'submissions' => $this->submissions->page($page, self::ADMIN_PAGE_SIZE),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    private function showSubmission(Request $request, string $id): Response
    {
        $userId = $this->authenticator->currentUserId();
        if ($userId === null) {
            return Response::redirect('/admin/login');
        }
        $submission = $this->submissions->find($id);
        if ($submission === null) {
            return $this->error(404, 'Page not found', 'The requested page does not exist.');
        }
        $this->audit->record('submission_viewed', $userId, $request->clientAddress, $id);

        return Response::html($this->view->page('admin/submission', 'Submission', [
            'stored' => $submission,
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    private function error(int $status, string $title, string $message): Response
    {
        return Response::html($this->view->page('errors/error', $title, ['heading' => $title, 'message' => $message]), $status);
    }

    private function securityHeaders(): array
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];
        if ($this->secureTransport) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}
