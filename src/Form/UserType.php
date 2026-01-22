<?php

declare(strict_types=1);

namespace App\Form;

use App\Constants\User\AccountStatus;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
                ->add('accountStatus', EnumType::class, [
                    'class' => AccountStatus::class,
                    'label' => 'form.account_status.label',
                    'choice_label' => fn (AccountStatus $status) => 'user.status.'.$status->value,
                    'attr' => ['class' => 'form-select'],
                    'placeholder' => 'form.account_status.placeholder',
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
