<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * IMPORTANT: the Submit button is NOT added to the form on purpose — it
 * is rendered manually in the template as a Bootstrap-styled <button>.
 *
 * NOTE: if the submit field stays in the form type, Symfony will auto-render
 * it inside form_end() — producing two "Register" buttons on the page.
 *
 * IMPORTANT: we also give the form an explicit CSRF field name and token id,
 * tied to a STATIC id "register". This keeps the token stable across renders
 * (stateless HMAC) and avoids accidental coupling to the auto-generated
 * form name (`registration_form`), which Symfony otherwise uses as the
 * default token id — and which behaves inconsistently when the session
 * gets invalidated mid-flow (e.g. after self-delete → /logout).
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('email')
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm password'],
                'invalid_message' => 'The passwords do not match.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter a password.'),
                    new Assert\Length(
                        min: 1,
                        minMessage: 'Your password must be at least {{ limit }} characters long.',
                    ),
                ],
            ])
            // NOTE: no submit field — the template provides the button.
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // IMPORTANT: pin the CSRF token id to a stable string so that
            // the generated token survives session invalidation.
            'csrf_token_id' => 'register',
            'csrf_field_name' => '_csrf_token',
        ]);
    }
}