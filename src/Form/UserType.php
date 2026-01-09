<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['data'] instanceof User && null !== $options['data']->getId();
        $isAdmin = $options['is_admin'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'Please enter an email address.'),
                    new Email(message: 'Please enter a valid email address.'),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ]);

        if (!$isEdit) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Password',
                'constraints' => [
                    new NotBlank(message: 'Please enter a password.'),
                ],
                'attr' => ['class' => 'form-control'],
            ]);
        }

        // Only admins can modify roles and status
        if ($isAdmin) {
            $builder
                ->add('isActive', CheckboxType::class, [
                    'label' => 'Active',
                    'required' => false,
                    'attr' => ['class' => 'form-check-input'],
                ])
                ->add('roles', ChoiceType::class, [
                    'label' => 'Roles',
                    'multiple' => true,
                    'expanded' => true,
                    'choices' => [
                        'User' => 'ROLE_USER',
                        'Administrator' => 'ROLE_ADMIN',
                    ],
                    'attr' => ['class' => 'form-check-input'],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_admin' => false,
        ]);
    }
}
