<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IMPORTANT: confirmation-link endpoint. NOTE: open to anonymous users —
 * the InactiveUserSubscriber excludes `app_verify_email` from its check.
 */
final class VerifyEmailController extends AbstractController
{
    #[Route('/verify', name: 'app_verify_email', methods: ['GET'])]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): Response {
        // IMPORTANT: the verification link built by SendVerificationEmailHandler
        // puts the token in the query string (?token=...), so we read it from
        // $request rather than declaring it as a route argument.
        $token = (string) $request->query->get('token', '');

        $user = $userRepository->findByVerificationToken($token);

        if ($user === null) {
            return $this->render('user/verify_result.html.twig', [
                'success' => false,
                'message' => 'Verification link is invalid or has expired.',
            ]);
        }

        // NOTE: blocked users stay blocked even after clicking the link.
        if ($user->getStatus() === UserStatus::Blocked) {
            return $this->render('user/verify_result.html.twig', [
                'success' => false,
                'message' => 'Your account is blocked. Please contact the administrator.',
            ]);
        }

        $user->setStatus(UserStatus::Active);
        // IMPORTANT: invalidate the token after successful verification.
        $user->setEmailVerificationToken(null);
        $em->flush();

        return $this->render('user/verify_result.html.twig', [
            'success' => true,
            'message' => 'Your e-mail has been confirmed. You can now log in.',
        ]);
    }
}