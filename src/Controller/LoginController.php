<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling login view and form processing.
 */
class LoginController extends AbstractController
{
    private const SESSION_AUTH_KEY = 'authenticated';
    private const SESSION_TIMEOUT = 1800; // 30 minutes in seconds

    /**
     * Display login form.
     */
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        // Check if already authenticated
        if ($this->isAuthenticated($request->getSession())) {
            return $this->redirect('/');
        }

        // Check for expired session parameter
        $expired = $request->query->getBoolean('expired', false);

        // Create form
        $form = $this->createForm(LoginFormType::class);

        return $this->render('login/index.html.twig', [
            'form' => $form->createView(),
            'error' => null,
            'expired' => $expired,
        ]);
    }

    /**
     * Process login form submission.
     */
    #[Route('/login', name: 'app_login_post', methods: ['POST'])]
    public function loginPost(
        Request $request,
        SessionInterface $session
    ): Response {
        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->render('login/index.html.twig', [
                'form' => $form->createView(),
                'error' => null,
                'expired' => false,
            ]);
        }

        if (!$form->isValid()) {
            // Get validation errors
            $errors = $form->getErrors(true);
            $errorMessage = null;
            if (count($errors) > 0) {
                $errorMessage = $errors[0]->getMessage();
            }
            
            return $this->render('login/index.html.twig', [
                'form' => $form->createView(),
                'error' => $errorMessage ?? 'Wystąpił błąd walidacji formularza.',
                'expired' => false,
            ]);
        }

        $password = $form->get('password')->getData();
        $expectedPassword = $_ENV['APP_PASSWORD'] ?? null;

        if ($expectedPassword === null) {
            return $this->render('login/index.html.twig', [
                'form' => $form->createView(),
                'error' => 'Błąd konfiguracji serwera. Skontaktuj się z administratorem.',
                'expired' => false,
            ], new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));
        }

        if (!hash_equals($expectedPassword, $password)) {
            return $this->render('login/index.html.twig', [
                'form' => $form->createView(),
                'error' => 'Nieprawidłowe hasło. Spróbuj ponownie.',
                'expired' => false,
            ]);
        }

        // Set authenticated session
        $session->set(self::SESSION_AUTH_KEY, true);
        $session->set('auth_time', time());

        // Redirect to home page
        return $this->redirect('/');
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

