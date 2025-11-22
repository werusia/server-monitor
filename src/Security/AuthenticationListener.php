<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that validates session authentication for API endpoints.
 * Checks if user is authenticated via session before allowing access to protected endpoints.
 */
class AuthenticationListener implements EventSubscriberInterface
{
    private const SESSION_AUTH_KEY = 'authenticated';
    private const SESSION_TIMEOUT = 1800; // 30 minutes in seconds

    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip authentication check for login endpoint
        if ($path === '/api/login') {
            return;
        }

        // Only check authentication for /api/* endpoints
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Check if session is authenticated
        if (!$this->isAuthenticated()) {
            $event->setResponse(new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Authentication required',
                ],
                Response::HTTP_UNAUTHORIZED
            ));
        }
    }

    private function isAuthenticated(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        $session = $request->getSession();
        if (!$session->has(self::SESSION_AUTH_KEY)) {
            return false;
        }

        if ($session->get(self::SESSION_AUTH_KEY) !== true) {
            return false;
        }

        // Check session timeout (30 minutes of inactivity)
        $authTime = $session->get('auth_time');
        if ($authTime === null || (time() - $authTime) > self::SESSION_TIMEOUT) {
            $session->invalidate();
            return false;
        }

        // Update auth time on each request (refresh timeout)
        $session->set('auth_time', time());

        return true;
    }
}

