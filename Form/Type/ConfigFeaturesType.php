<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Form\Type;

use MauticPlugin\AiEmailSectionsBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Feature settings for an unlimited collection of selectable AI profiles. */
final class ConfigFeaturesType extends AbstractType
{
    public function __construct(private readonly Config $config)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = is_array($options['data'] ?? null) ? $options['data'] : [];
        $profiles = array_key_exists('profiles', $data) && is_array($data['profiles'])
            ? $data['profiles']
            : $this->config->getProfilesForForm();

        $builder->add('default_profile', ChoiceType::class, [
            'label'       => 'mautic.aiemailsections.profile.default',
            'choices'     => $this->profileChoices($profiles),
            'required'    => false,
            'placeholder' => false,
            'help'        => 'mautic.aiemailsections.profile.default.help',
        ]);

        $builder->add('profiles', CollectionType::class, [
            'label'         => 'mautic.aiemailsections.profile.collection',
            'entry_type'    => ProfileType::class,
            'entry_options' => ['label' => false],
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false,
            'prototype'     => true,
            'data'          => $profiles,
            'attr'           => ['class' => 'ai-profile-collection'],
            'help'           => 'mautic.aiemailsections.profile.collection.help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The IntegrationsBundle's feature-settings wrapper does not always
        // pass the integration object, while the auth form does.
        $resolver->setDefined(['integration']);
    }

    /** @param array<int, array<string, mixed>> $profiles */
    private function profileChoices(array $profiles): array
    {
        $choices = [];
        foreach ($profiles as $profile) {
            if (is_array($profile) && isset($profile['id'], $profile['name'])) {
                $label = (string) $profile['name'];
                if (isset($choices[$label])) {
                    $label .= ' ('.(string) $profile['id'].')';
                }
                $choices[$label] = (string) $profile['id'];
            }
        }

        return $choices;
    }
}
