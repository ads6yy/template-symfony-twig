<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends BaseUserType<array{email: string, firstName?: string, lastName?: string, password: string}|null>
 */
final class RegistrationType extends BaseUserType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addCommonFields($builder);
        $this->addPasswordField($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class, form returns an array
        ]);
    }
}
