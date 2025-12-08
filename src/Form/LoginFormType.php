<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for login form with password field.
 */
class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'Hasło',
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'current-password',
                    'aria-label' => 'Hasło',
                    'aria-required' => 'true',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Hasło jest wymagane.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Zaloguj',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg',
                    'aria-label' => 'Zaloguj się',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'login',
        ]);
    }
}
