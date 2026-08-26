<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

/**
 * Reads the plugin configuration.
 *
 * The API key lives in apiKeys, which the IntegrationsBundle encrypts and
 * decrypts on its own. Everything else lives in featureSettings, in the clear.
 */
class Config
{
    /**
     * "openai" is the legacy generic mode and covers every endpoint that
     * speaks the OpenAI shape, including LiteLLM, Ollama and LM Studio. Named
     * cloud providers supply tested defaults without changing that stored
     * value. Anthropic and Gemini use their native protocols.
     */
    public const PROVIDER_OPENAI        = 'openai';
    public const PROVIDER_OPENAI_CLOUD  = 'openai_cloud';
    public const PROVIDER_GOOGLE        = 'google';
    public const PROVIDER_DEEPSEEK      = 'deepseek';
    public const PROVIDER_QWEN          = 'qwen';
    public const PROVIDER_GROQ          = 'groq';
    public const PROVIDER_OPENROUTER    = 'openrouter';
    public const PROVIDER_ANTHROPIC     = 'anthropic';

    /**
     * Installs configured before this field existed have nothing stored and
     * must keep talking to the endpoint they were already talking to.
     */
    public const DEFAULT_PROVIDER = self::PROVIDER_OPENAI;

    public const DEFAULT_BASE_URL       = 'http://localhost:4000/v1';
    public const DEFAULT_MODEL          = 'qwen2.5:32b';

    public const DEFAULT_OPENAI_BASE_URL    = 'https://api.openai.com/v1';
    public const DEFAULT_OPENAI_MODEL       = 'gpt-4o-mini';
    public const DEFAULT_GOOGLE_BASE_URL    = 'https://generativelanguage.googleapis.com/v1beta';
    public const DEFAULT_GOOGLE_MODEL       = 'gemini-3.6-flash';
    public const DEFAULT_DEEPSEEK_BASE_URL  = 'https://api.deepseek.com';
    public const DEFAULT_DEEPSEEK_MODEL     = 'deepseek-v4-flash';
    public const DEFAULT_QWEN_BASE_URL      = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1';
    public const DEFAULT_QWEN_MODEL         = 'qwen-plus';
    public const DEFAULT_GROQ_BASE_URL      = 'https://api.groq.com/openai/v1';
    public const DEFAULT_GROQ_MODEL         = 'openai/gpt-oss-120b';
    public const DEFAULT_OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';
    public const DEFAULT_OPENROUTER_MODEL    = 'openai/gpt-4o-mini';
    public const DEFAULT_ANTHROPIC_BASE_URL = 'https://api.anthropic.com';
    /**
     * Sonnet rather than Opus on the strength of a 12-case comparison run
     * through this exact pipeline: same first-attempt rate, same validator
     * warnings, comparable copy, at roughly half the latency and half the cost.
     * It was also the only model to get both the layout and the copy right on
     * the hardest case. See tools/prompt-bench in the deployment repository.
     */
    public const DEFAULT_ANTHROPIC_MODEL    = 'claude-sonnet-5';
    public const DEFAULT_TEMPERATURE        = 0.4;
    public const DEFAULT_MAX_TOKENS         = 1500;
    public const DEFAULT_PLACEHOLDER        = 'https://placehold.co/600x400/eeeeee/999999.png?text=Image';
    public const DEFAULT_RATE_LIMIT         = 30;
    public const DEFAULT_THEME              = 'default';
    /**
     * Generous on purpose. A 32B model running locally spends most of this on
     * the cold start, loading tens of gigabytes into memory on the first call.
     * A hosted provider answers in a fraction of it and never notices.
     */
    public const DEFAULT_TIMEOUT        = 180;
    public const DEFAULT_MAX_SOURCE_KB  = 12;

    /**
     * The brief travels in the system prompt on every single generation, so an
     * unbounded one quietly makes each call more expensive.
     */
    public const MAX_BRAND_BRIEF_CHARS = 2000;

    public function __construct(
        private IntegrationsHelper $integrationsHelper,
    ) {
    }

    public function isPublished(): bool
    {
        try {
            return (bool) $this->getIntegrationEntity()->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    /**
     * @return self::PROVIDER_*
     */
    public function getProvider(): string
    {
        $provider = $this->string('provider', self::DEFAULT_PROVIDER);

        // The value comes out of a database column rather than out of the
        // form, so it can hold anything. Falling back beats fataling.
        return in_array($provider, array_keys(self::providerDefaults()), true)
            ? $provider
            : self::DEFAULT_PROVIDER;
    }

    /**
     * The default follows the provider, so switching the dropdown and saving
     * works without also retyping the endpoint.
     */
    public function getBaseUrl(): string
    {
        $default = self::providerDefaults()[$this->getProvider()]['base_url'];

        $url = $this->string('base_url', $default);

        // The value becomes the target of server-side requests, where only
        // http(s) has a legitimate use. Anything else falls back to the
        // provider default rather than turning the plugin into a proxy.
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $default;
        }

        return $url;
    }

    public function getApiKey(): string
    {
        try {
            $keys = $this->getIntegrationEntity()->getApiKeys();
        } catch (IntegrationNotFoundException) {
            return '';
        }

        return is_array($keys) && isset($keys['api_key']) ? (string) $keys['api_key'] : '';
    }

    public function getModel(): string
    {
        $default = self::providerDefaults()[$this->getProvider()]['model'];

        return $this->string('model', $default);
    }

    public function getTemperature(): float
    {
        $settings = $this->getFeatureSettings();

        return isset($settings['temperature']) ? (float) $settings['temperature'] : self::DEFAULT_TEMPERATURE;
    }

    public function getMaxTokens(): int
    {
        return $this->int('max_tokens', self::DEFAULT_MAX_TOKENS);
    }

    /**
     * Free text describing what the company sells and how it speaks. Empty on a
     * fresh install: the plugin works without it, it just writes more generic
     * copy.
     */
    public function getBrandBrief(): string
    {
        return $this->string('brand', '');
    }

    /**
     * On by default. A generator that cannot personalise is the poorer default,
     * and the list is small: 58 tokens is roughly 550 input tokens per call.
     */
    public function areTokensEnabled(): bool
    {
        $settings = $this->getFeatureSettings();

        return !isset($settings['tokens_enabled']) || (bool) $settings['tokens_enabled'];
    }

    /**
     * Tokens the administrator does not want offered. Accepts one per line or
     * comma separated, with or without braces, because both are what someone
     * types after copying from the builder.
     *
     * @return string[]
     */
    public function getExcludedTokens(): array
    {
        $raw = $this->string('tokens_excluded', '');

        if ('' === $raw) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('~[\r\n,]+~', $raw) ?: [])));
    }

    public function getPlaceholderImage(): string
    {
        return $this->string('placeholder_image', self::DEFAULT_PLACEHOLDER);
    }

    public function getRateLimitPerHour(): int
    {
        return $this->int('rate_limit', self::DEFAULT_RATE_LIMIT);
    }

    public function getThemeId(): string
    {
        return $this->string('theme', self::DEFAULT_THEME);
    }

    public function getTimeoutSeconds(): int
    {
        return $this->int('timeout', self::DEFAULT_TIMEOUT);
    }

    public function getMaxSourceBytes(): int
    {
        return self::DEFAULT_MAX_SOURCE_KB * 1024;
    }

    /**
     * IntegrationFeatureSettingsType nests the plugin's own form under an
     * "integration" key, so what gets stored is
     * ['integration' => ['base_url' => ...]] and not ['base_url' => ...].
     *
     * Reading the top level only would silently fall back to the defaults and
     * ignore everything the user configured.
     *
     * @return mixed[]
     */
    public function getFeatureSettings(): array
    {
        try {
            $settings = $this->getIntegrationEntity()->getFeatureSettings() ?: [];
        } catch (IntegrationNotFoundException) {
            return [];
        }

        if (isset($settings['integration']) && is_array($settings['integration'])) {
            return $settings['integration'] + $settings;
        }

        return $settings;
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        return $this->integrationsHelper->getIntegration(AiEmailSectionsIntegration::NAME)
            ->getIntegrationConfiguration();
    }

    private function string(string $key, string $default): string
    {
        $settings = $this->getFeatureSettings();
        $value    = isset($settings[$key]) ? trim((string) $settings[$key]) : '';

        return '' === $value ? $default : $value;
    }

    private function int(string $key, int $default): int
    {
        $settings = $this->getFeatureSettings();

        return isset($settings[$key]) && '' !== $settings[$key] ? (int) $settings[$key] : $default;
    }

    /**
     * @return array<string, array{base_url: string, model: string}>
     */
    private static function providerDefaults(): array
    {
        return [
            self::PROVIDER_OPENAI       => ['base_url' => self::DEFAULT_BASE_URL, 'model' => self::DEFAULT_MODEL],
            self::PROVIDER_OPENAI_CLOUD => ['base_url' => self::DEFAULT_OPENAI_BASE_URL, 'model' => self::DEFAULT_OPENAI_MODEL],
            self::PROVIDER_GOOGLE       => ['base_url' => self::DEFAULT_GOOGLE_BASE_URL, 'model' => self::DEFAULT_GOOGLE_MODEL],
            self::PROVIDER_DEEPSEEK     => ['base_url' => self::DEFAULT_DEEPSEEK_BASE_URL, 'model' => self::DEFAULT_DEEPSEEK_MODEL],
            self::PROVIDER_QWEN         => ['base_url' => self::DEFAULT_QWEN_BASE_URL, 'model' => self::DEFAULT_QWEN_MODEL],
            self::PROVIDER_GROQ         => ['base_url' => self::DEFAULT_GROQ_BASE_URL, 'model' => self::DEFAULT_GROQ_MODEL],
            self::PROVIDER_OPENROUTER   => ['base_url' => self::DEFAULT_OPENROUTER_BASE_URL, 'model' => self::DEFAULT_OPENROUTER_MODEL],
            self::PROVIDER_ANTHROPIC    => ['base_url' => self::DEFAULT_ANTHROPIC_BASE_URL, 'model' => self::DEFAULT_ANTHROPIC_MODEL],
        ];
    }
}
