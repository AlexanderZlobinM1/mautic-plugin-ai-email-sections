<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Auth tab. Everything entered here is encrypted by the IntegrationsBundle
 * before being stored, and is never serialised to the client.
 */
class ConfigAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('api_key', PasswordType::class, [
            'label'      => 'mautic.aiemailsections.config.api_key.fallback',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'        => 'form-control',
                'tooltip'      => 'mautic.aiemailsections.config.api_key.tooltip',
                'autocomplete' => 'off',
            ],
            'required'         => false,
            'always_empty'     => false,
        ]);

        $integrationObject = $options['integration'];
        $integration = is_object($integrationObject) && method_exists($integrationObject, 'getIntegrationConfiguration')
            ? $integrationObject->getIntegrationConfiguration()
            : $integrationObject;
        $settings = is_object($integration) && method_exists($integration, 'getFeatureSettings')
            ? (array) $integration->getFeatureSettings()
            : [];
        $settings = isset($settings['integration']) && is_array($settings['integration'])
            ? $settings['integration'] + $settings
            : $settings;
        $rawProfiles = is_array($settings['profiles'] ?? null) ? $settings['profiles'] : [];
        $apiKeys = is_object($integration) && method_exists($integration, 'getApiKeys')
            ? (array) $integration->getApiKeys()
            : [];
        $profileKeys = is_array($apiKeys['profiles'] ?? null) ? $apiKeys['profiles'] : [];
        $profileData = [];

        foreach ($rawProfiles as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $id = trim((string) ($profile['id'] ?? (!is_numeric($key) ? $key : '')));
            if ('' === $id) {
                continue;
            }
            $profileData[$id] = ['api_key' => (string) ($profileKeys[$id] ?? '')];
        }

        if ([] !== $profileData) {
            $builder->add('profiles', CollectionType::class, [
                'label'         => 'mautic.aiemailsections.config.api_keys.profiles',
                'entry_type'    => ProfileApiKeyType::class,
                'entry_options' => ['label' => false],
                'allow_add'     => false,
                'allow_delete'  => false,
                'data'          => $profileData,
            ]);
        }
    }

    /**
     * The IntegrationsBundle always passes the Integration to the form type.
     * Without declaring it here the configuration modal blows up with an alert
     * instead of opening.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['integration']);
    }
}
