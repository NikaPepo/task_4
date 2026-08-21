<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

/**
 * IMPORTANT: requirement #5 — "before each request except for registration
 * or login, server should check if user exists and isn't blocked".
 *
 * NOTE: this subscriber runs after the firewall on every request that
 * reaches the controller layer. If the authenticated user is blocked,
 * or has been deleted from the DB, we invalidate the session and redirect
 * to the login page with an explanatory flash message.
 *
 * Implementation detail: we cannot read the firewall's redirect from inside
 * a kernel.request listener (the response is sent too late), so we manually
 * replace the request's response via setResponse() and abort further
 * listener processing by returning early.
 */
final readonly class InactiveUserSubscriber implements EventSubscriberInterface
{
    /**
     * IMPORTANT: routes that bypass the check (per the task wording).
     */
    private const ALLOWED_ROUTES = ['app_login', 'app_registration', 'app_verify_email'];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UserRepository $userRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // NOTE: priority < 8 — the firewall runs at 8, we want to run after.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        // NOTE: only act on real authenticated tokens, not SwitchUser / RememberMe wrappers.
        if ($token === null || $token instanceof SwitchUserToken) {
            return;
        }
        if (!$this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // IMPORTANT: re-fetch the user from the DB to catch status changes
        // that happened in another request (or by another admin).
        $fresh = $this->userRepository->find($user->getId());

        $reason = null;
        if ($fresh === null) {
            $reason = 'Your account has been deleted.';
        } elseif ($fresh->getStatus() === UserStatus::Blocked) {
            $reason = 'Your account has been blocked.';
        }

        if ($reason !== null) {
            // NOTE: kill the session and force the user back to login.
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
            // Carry the flash message across the redirect by stashing it in the session.
            $request->getSession()->getFlashBag()->add('danger', $reason);
            $event->setResponse($response);
        }
    }
}