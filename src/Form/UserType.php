<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends BaseUserType<User|null>
 */
final class UserType extends BaseUserType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['data'] instanceof User && null !== $options['data']->getId();
        $isAdmin = $options['is_admin'] ?? false;

        $this->addCommonFields($builder);

        if (!$isEdit) {
            $this->addPasswordField($builder);
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
