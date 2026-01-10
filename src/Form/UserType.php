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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['data'] instanceof User && null !== $options['data']->getId();
        $isAdmin = $options['is_admin'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email.label',
                'constraints' => [
                    new NotBlank(message: 'validation.email.not_blank'),
                    new Email(message: 'validation.email.invalid'),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->translator->trans('form.email.placeholder'),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'form.first_name.label',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->translator->trans('form.first_name.placeholder'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'form.last_name.label',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->translator->trans('form.last_name.placeholder'),
                ],
            ]);

        if (!$isEdit) {
            $builder->add('password', PasswordType::class, [
                'label' => 'form.password.label',
                'constraints' => [
                    new NotBlank(message: 'validation.password.not_blank'),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->translator->trans('form.password.placeholder'),
                ],
            ]);
        }

        // Only admins can modify roles and status
        if ($isAdmin) {
            $builder
                ->add('isActive', CheckboxType::class, [
                    'label' => 'form.active.label',
                    'required' => false,
                    'attr' => ['class' => 'form-check-input'],
                ])
                ->add('roles', ChoiceType::class, [
                    'label' => 'form.roles.label',
                    'multiple' => true,
                    'expanded' => true,
                    'choices' => [
                        'form.roles.user' => 'ROLE_USER',
                        'form.roles.admin' => 'ROLE_ADMIN',
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
