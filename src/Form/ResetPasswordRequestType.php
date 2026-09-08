<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<array{email: string}|null>
 */
final class ResetPasswordRequestType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'form.email.label',
            'attr' => [
                'class' => 'form-control',
                'placeholder' => $this->translator->trans('form.email.placeholder'),
                'autofocus' => true,
            ],
            'constraints' => [
                new NotBlank(message: 'validation.email.not_blank'),
                new Email(message: 'validation.email.invalid'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class, form returns an array
        ]);
    }
}
