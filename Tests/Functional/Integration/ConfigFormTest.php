<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Functional\Integration;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\AiEmailSectionsBundle\Form\Type\ConfigAuthType;
use MauticPlugin\AiEmailSectionsBundle\Form\Type\ConfigFeaturesType;
use MauticPlugin\AiEmailSectionsBundle\Form\Type\ProfileApiKeyType;
use MauticPlugin\AiEmailSectionsBundle\Form\Type\ProfileType;
use MauticPlugin\AiEmailSectionsBundle\Integration\AiEmailSectionsIntegration;
use Symfony\Component\Form\FormFactoryInterface;

/** Configuration covers unlimited profiles while keeping keys encrypted. */
final class ConfigFormTest extends MauticMysqlTestCase
{
    public function testFeatureSettingsFormBuildsTheProfileCollection(): void
    {
        $form = $this->formFactory()->create(ConfigFeaturesType::class);

        $this->assertTrue($form->has('default_profile'));
        $this->assertTrue($form->has('profiles'));
        $this->assertSame(ProfileType::class, $form->get('profiles')->getConfig()->getOption('entry_type'));
        $this->assertTrue($form->get('profiles')->getConfig()->getOption('allow_add'));
        $this->assertTrue($form->get('profiles')->getConfig()->getOption('allow_delete'));
    }

    public function testEveryProfileOffersExactlyTheSupportedProviders(): void
    {
        $form = $this->formFactory()->create(ConfigFeaturesType::class);
        $entry = $this->formFactory()->create(ProfileType::class);
        $choices = $entry->get('provider')->getConfig()->getOption('choices');

        $this->assertSame(
            [
                'openai', 'openai_cloud', 'google', 'deepseek',
                'qwen', 'groq', 'openrouter', 'anthropic',
            ],
            array_values($choices)
        );
        $this->assertTrue($form->has('profiles'));
    }

    public function testApiKeysStayOnTheEncryptedAuthTab(): void
    {
        $features = $this->formFactory()->create(ConfigFeaturesType::class);
        $this->assertFalse($features->has('api_key'));

        $auth = $this->formFactory()->create(ConfigAuthType::class, [], [
            'integration' => $this->integration(),
        ]);
        $this->assertTrue($auth->has('api_key'));
    }

    public function testProfileApiKeysAreRenderedForSavedProfiles(): void
    {
        $integration = $this->integration();
        $integration->setFeatureSettings([
            'integration' => [
                'profiles' => [[
                    'id'   => 'local-qwen',
                    'name' => 'Local Qwen',
                ]],
            ],
        ]);

        $form = $this->formFactory()->create(ConfigAuthType::class, [], ['integration' => $integration]);

        $this->assertTrue($form->has('profiles'));
        $this->assertSame(ProfileApiKeyType::class, $form->get('profiles')->getConfig()->getOption('entry_type'));
    }

    public function testSavedProfileCanTurnPersonalisationTokensOff(): void
    {
        $form = $this->formFactory()->create(ConfigFeaturesType::class, [
            'profiles' => [[
                'id'             => 'minimal',
                'name'           => 'Minimal',
                'tokens_enabled' => false,
            ]],
        ]);

        $profile = $form->get('profiles')->get(0);
        $this->assertFalse($profile->get('tokens_enabled')->getData());
    }

    private function formFactory(): FormFactoryInterface
    {
        return static::getContainer()->get('form.factory');
    }

    private function integration(): Integration
    {
        $integration = new Integration();
        $integration->setName(AiEmailSectionsIntegration::NAME);
        $integration->setIsPublished(true);

        return $integration;
    }
}
