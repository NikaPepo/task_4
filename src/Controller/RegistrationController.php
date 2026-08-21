<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\RegistrationFormType;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IMPORTANT: open to anonymous visitors; the InactiveUserSubscriber
 * skips the user-existence check for `app_registration`.
 */
final class RegistrationController extends AbstractController
{
    #[Route('/registration', name: 'app_registration', methods: ['GET', 'POST'])]
    public function index(Request $request,
                          RegistrationService $registrationService): Response
    {
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $registrationService->register(
                $form->get('name')->getData(),
                $form->get('email')->getData(),
                $form->get('plainPassword')->getData(),
            );

            // IMPORTANT: per the task — "Users are registered right away
            // and the corresponding message is shown after the registration;
            // the confirmation e-mail should be sent asynchronously."
            $this->addFlash(
                'success',
                sprintf(
                    'Account "%s" has been created. A confirmation link has been sent to %s.',
                    $user->getName(),
                    $user->getEmail(),
                )
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/index.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}