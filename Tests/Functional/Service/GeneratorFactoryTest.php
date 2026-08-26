<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Functional\Service;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\AiEmailSectionsBundle\Integration\AiEmailSectionsIntegration;
use MauticPlugin\AiEmailSectionsBundle\Integration\Config;
use MauticPlugin\AiEmailSectionsBundle\Service\AnthropicClient;
use MauticPlugin\AiEmailSectionsBundle\Service\GeneratorFactory;
use MauticPlugin\AiEmailSectionsBundle\Service\GeminiClient;
use MauticPlugin\AiEmailSectionsBundle\Service\OpenAiCompatibleClient;

/**
 * The provider is stored on the Integration and read at runtime, so the wrong
 * branch here is a 400 from the provider and nothing else. Cheap to pin down.
 */
final class GeneratorFactoryTest extends MauticMysqlTestCase
{
    public function testBuildsTheOpenAiCompatibleClientByDefault(): void
    {
        $this->persistIntegration([]);

        $this->assertInstanceOf(OpenAiCompatibleClient::class, $this->factory()->createClient());
    }

    public function testBuildsTheAnthropicClientWhenThatProviderIsChosen(): void
    {
        $this->persistIntegration(['integration' => ['provider' => Config::PROVIDER_ANTHROPIC]]);

        $this->assertInstanceOf(AnthropicClient::class, $this->factory()->createClient());
    }

    public function testBuildsTheGeminiClientWhenGoogleIsChosen(): void
    {
        $this->persistIntegration(['integration' => ['provider' => Config::PROVIDER_GOOGLE]]);

        $this->assertInstanceOf(GeminiClient::class, $this->factory()->createClient());
    }

    /**
     * @dataProvider openAiCompatibleCloudProviders
     */
    public function testBuildsTheOpenAiCompatibleClientForCompatibleCloudProviders(string $provider): void
    {
        $this->persistIntegration(['integration' => ['provider' => $provider]]);

        $this->assertInstanceOf(OpenAiCompatibleClient::class, $this->factory()->createClient());
    }

    /** @return iterable<string, array{string}> */
    public static function openAiCompatibleCloudProviders(): iterable
    {
        yield 'OpenAI' => [Config::PROVIDER_OPENAI_CLOUD];
        yield 'DeepSeek' => [Config::PROVIDER_DEEPSEEK];
        yield 'Qwen' => [Config::PROVIDER_QWEN];
        yield 'Groq' => [Config::PROVIDER_GROQ];
        yield 'OpenRouter' => [Config::PROVIDER_OPENROUTER];
    }

    /**
     * Pointing the OpenAI-compatible client at a proxy is the other way to
     * reach Anthropic, and it must stay available.
     */
    public function testKeepsTheOpenAiCompatibleClientWhenTheProviderIsAProxy(): void
    {
        $this->persistIntegration([
            'integration' => [
                'provider' => Config::PROVIDER_OPENAI,
                'base_url' => 'http://litellm.internal/v1',
                'model'    => 'claude-opus-5',
            ],
        ]);

        $this->assertInstanceOf(OpenAiCompatibleClient::class, $this->factory()->createClient());
    }

    private function factory(): GeneratorFactory
    {
        return static::getContainer()->get(GeneratorFactory::class);
    }

    /**
     * @param mixed[] $featureSettings
     */
    private function persistIntegration(array $featureSettings): void
    {
        $integration = new Integration();
        $integration->setName(AiEmailSectionsIntegration::NAME);
        $integration->setIsPublished(true);
        $integration->setFeatureSettings($featureSettings);

        $this->em->persist($integration);
        $this->em->flush();
        $this->em->clear();
    }
}
