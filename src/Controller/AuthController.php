<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling authentication endpoints.
 */
class AuthController extends AbstractController
{
    private const SESSION_AUTH_KEY = 'authenticated';
    private const SESSION_TIMEOUT = 1800; // 30 minutes in seconds

    /**
     * Authenticate user with password from .env configuration.
     *
     * Request body: { "password": "string" }
     * Response: { "success": true, "message": "Authentication successful" }
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Invalid JSON format',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!isset($data['password']) || !is_string($data['password']) || empty(trim($data['password']))) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Password is required',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $providedPassword = trim($data['password']);
        $expectedPassword = $_ENV['APP_PASSWORD'] ?? null;

        if ($expectedPassword === null) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Server configuration error',
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        if (!hash_equals($expectedPassword, $providedPassword)) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Invalid password',
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Set authenticated session
        $session->set(self::SESSION_AUTH_KEY, true);
        $session->set('auth_time', time());

        return new JsonResponse(
            [
                'success' => true,
                'message' => 'Authentication successful',
            ]
        );
    }

    /**
     * End user session and invalidate authentication.
     *
     * Response: { "success": true, "message": "Logged out successfully" }
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(SessionInterface $session): JsonResponse
    {
        $session->invalidate();

        return new JsonResponse(
            [
                'success' => true,
                'message' => 'Logged out successfully',
            ]
        );
    }
}
