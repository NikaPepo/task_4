<?php

declare(strict_types=1);

namespace App\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * IMPORTANT: handles the login form rendering and the logout stub.
 * NOTE: the firewall intercepts the actual `POST /login` and redirects
 * to `app_login` on failure / to a configured target on success.
 */
final class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // NOTE: if a logged-in user re-visits /login, send them to the admin table.
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_users');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /**
     * IMPORTANT: never reached — the security firewall intercepts the request.
     * NOTE: must exist as a route for the firewall's logout configuration.
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): void
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}