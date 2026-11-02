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
        $legacyKey   = (string) ($apiKeys['api_key'] ?? '');
        $profileData = [];

        foreach ($rawProfiles as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $id = trim((string) ($profile['id'] ?? (!is_numeric($key) ? $key : '')));
            if ('' === $id) {
                continue;
            }
            $storedKey = $profileKeys[$id] ?? $legacyKey;
            // 3.0.0 briefly rendered each key as a nested form. Read that
            // shape as well so an existing encrypted value is not lost when
            // the auth form is normalised to profiles[id] => string.
            if (is_array($storedKey)) {
                $storedKey = $storedKey['api_key'] ?? '';
            }
            $profileData[$id] = (string) $storedKey;
        }

        // The controls are rendered by the encrypted auth form, then moved by
        // config.js into their matching profile editor on the Features tab.
        // Keeping their names under apiKeys means Mautic still encrypts them.
        $builder->add('profiles', CollectionType::class, [
            'label'         => false,
            'entry_type'    => PasswordType::class,
            'entry_options' => [
                'label'        => false,
                'required'     => false,
                'always_empty' => false,
                'attr'         => [
                    'class'        => 'form-control',
                    'autocomplete' => 'off',
                ],
            ],
            'allow_add'      => true,
            'allow_delete'   => true,
            'by_reference'   => false,
            'data'           => $profileData,
            'required'       => false,
        ]);
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
