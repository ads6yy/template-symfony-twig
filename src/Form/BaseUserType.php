<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template TData
 *
 * @extends AbstractType<TData>
 */
abstract class BaseUserType extends AbstractType
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param FormBuilderInterface<TData> $builder
     */
    protected function addCommonFields(FormBuilderInterface $builder): void
    {
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
    }

    /**
     * @param FormBuilderInterface<TData> $builder
     */
    protected function addPasswordField(FormBuilderInterface $builder): void
    {
        $builder->add('password', PasswordType::class, [
            'label' => 'form.password.label',
            'constraints' => [
                new NotBlank(message: 'validation.password.not_blank'),
                new Length(
                    min: 8,
                    max: 4096,
                    minMessage: 'validation.password.min_length'
                ),
                new NotCompromisedPassword(message: 'validation.password.compromised'),
            ],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => $this->translator->trans('form.password.placeholder'),
            ],
        ]);
    }
}
