<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{oldPassword?: string, newPassword: string, confirmPassword: string}>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requireOldPassword = $options['require_old_password'] ?? true;

        if ($requireOldPassword) {
            $builder->add('oldPassword', PasswordType::class, [
                'label' => 'Old Password',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter your old password.'
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Your current password',
                ],
                'mapped' => false,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => 'New Password',
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a password.'
                    ),
                    new Length(
                        min: 8,
                        minMessage: 'The password must contain at least {{ limit }} characters.',
                        max: 4096
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Minimum 8 characters',
                ],
                'mapped' => false,
                'help' => 'The password must contain at least 8 characters and be strong enough.',
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirm Password',
                'constraints' => [
                    new NotBlank(
                        message: 'Please confirm your password.'
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Retype your password',
                ],
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'require_old_password' => true,
        ]);
    }
}
