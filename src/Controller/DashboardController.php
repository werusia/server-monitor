<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling dashboard view.
 */
class DashboardController extends AbstractController
{
    private const SESSION_AUTH_KEY = 'authenticated';
    private const SESSION_TIMEOUT = 1800; // 30 minutes in seconds

    /**
     * Redirect root path to dashboard or login.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        if ($this->isAuthenticated($request->getSession())) {
            return $this->redirect('/dashboard');
        }
        
        return $this->redirect('/login');
    }

    /**
     * Display dashboard with server metrics.
     */
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Check if authenticated, redirect to login if not
        if (!$this->isAuthenticated($request->getSession())) {
            return $this->redirect('/login');
        }

        return $this->render('dashboard/index.html.twig');
    }

    private function isAuthenticated(SessionInterface $session): bool
    {
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

