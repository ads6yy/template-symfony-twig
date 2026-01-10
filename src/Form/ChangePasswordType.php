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
                'label' => 'form.old_password.label',
                'constraints' => [
                    new NotBlank(
                        message: 'validation.password.not_blank'
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'form.old_password.placeholder',
                ],
                'mapped' => false,
            ]);
        }

        $builder
            ->add('newPassword', PasswordType::class, [
                'label' => 'form.new_password.label',
                'constraints' => [
                    new NotBlank(
                        message: 'validation.password.not_blank'
                    ),
                    new Length(
                        min: 8,
                        minMessage: 'validation.password.min_length',
                        max: 4096
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'form.new_password.placeholder',
                ],
                'mapped' => false,
                'help' => 'form.new_password.help',
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'form.confirm_password.label',
                'constraints' => [
                    new NotBlank(
                        message: 'validation.password.confirm'
                    ),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'form.confirm_password.placeholder',
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
