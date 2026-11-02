<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** A profile key is rendered on the encrypted integration-auth tab. */
final class ProfileApiKeyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('api_key', PasswordType::class, [
            'label'        => $options['profile_name'] ?: $options['profile_id'],
            'required'     => false,
            'always_empty' => false,
            'attr'         => ['autocomplete' => 'off'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'profile_id'   => '',
            'profile_name' => '',
        ]);
    }
}
