<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{email: string, firstName?: string, lastName?: string, password: string}>
 */
final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email.label',
                'constraints' => [
                    new NotBlank(message: 'validation.email.not_blank'),
                    new Email(message: 'validation.email.invalid'),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'form.email.placeholder'],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'form.first_name.label',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'form.first_name.placeholder'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'form.last_name.label',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'form.last_name.placeholder'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.password.label',
                'constraints' => [
                    new NotBlank(message: 'validation.password.not_blank'),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'form.password.placeholder'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class, form returns an array
        ]);
    }
}
