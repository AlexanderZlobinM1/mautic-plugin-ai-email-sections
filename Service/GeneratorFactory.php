<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

use MauticPlugin\AiEmailSectionsBundle\Integration\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the GeneratorService from the configuration stored on the Integration.
 *
 * It exists because the configuration is read from the database at runtime and
 * cannot be injected as a container parameter.
 */
class GeneratorFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
        private readonly BuilderTokenProvider $tokenProvider,
    ) {
    }

    public function create(?string $profileId = null): GeneratorService
    {
        return new GeneratorService(
            $this->createClient($profileId),
            new PromptBuilder(
                $this->promptsDir(),
                $this->config->getPlaceholderImage($profileId),
                $this->config->getBrandBrief($profileId),
                $this->availableTokens($profileId),
            ),
            new MjmlValidator($this->config->getPlaceholderImage($profileId)),
        );
    }

    /**
     * Public because it is the one branch worth pinning down on its own: the
     * wrong client for the configured provider is a 400 and nothing else.
     */
    public function createClient(?string $profileId = null): LlmClientInterface
    {
        if (Config::PROVIDER_GOOGLE === $this->config->getProvider($profileId)) {
            return new GeminiClient(
                $this->httpClient,
                $this->config->getBaseUrl($profileId),
                $this->config->getApiKeyForProfile($profileId),
                $this->config->getModel($profileId),
                $this->config->getTemperature($profileId),
                $this->config->getMaxTokens($profileId),
                $this->config->getTimeoutSeconds($profileId),
            );
        }

        if (Config::PROVIDER_ANTHROPIC === $this->config->getProvider($profileId)) {
            return new AnthropicClient(
                $this->httpClient,
                $this->config->getBaseUrl($profileId),
                $this->config->getApiKeyForProfile($profileId),
                $this->config->getModel($profileId),
                $this->config->getMaxTokens($profileId),
                $this->config->getTimeoutSeconds($profileId),
            );
        }

        return new OpenAiCompatibleClient(
            $this->httpClient,
            $this->config->getBaseUrl($profileId),
            $this->config->getApiKeyForProfile($profileId),
            $this->config->getModel($profileId),
            $this->config->getTemperature($profileId),
            $this->config->getMaxTokens($profileId),
            $this->config->getTimeoutSeconds($profileId),
        );
    }

    /**
     * @return array<string, string>
     */
    private function availableTokens(?string $profileId = null): array
    {
        if (!$this->config->areTokensEnabled($profileId)) {
            return [];
        }

        return $this->tokenProvider->all($this->config->getExcludedTokens($profileId));
    }

    private function promptsDir(): string
    {
        return __DIR__.'/../Resources/prompts';
    }
}
