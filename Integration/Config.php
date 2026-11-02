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
    public function getProvider(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['provider'];
    }

    /**
     * The default follows the provider, so switching the dropdown and saving
     * works without also retyping the endpoint.
     */
    public function getBaseUrl(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['base_url'];
    }

    public function getApiKey(): string
    {
        return $this->getApiKeyForProfile();
    }

    public function getApiKeyForProfile(?string $profileId = null): string
    {
        $profile = $this->getProfile($profileId, false);

        return $this->apiKeyFor((string) $profile['id']);
    }

    public function getModel(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['model'];
    }

    public function getTemperature(?string $profileId = null): float
    {
        return (float) $this->getProfile($profileId)['temperature'];
    }

    public function getMaxTokens(?string $profileId = null): int
    {
        return (int) $this->getProfile($profileId)['max_tokens'];
    }

    public function getBrandBrief(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['brand'];
    }

    public function areTokensEnabled(?string $profileId = null): bool
    {
        return (bool) $this->getProfile($profileId)['tokens_enabled'];
    }

    /** @return string[] */
    public function getExcludedTokens(?string $profileId = null): array
    {
        return (array) $this->getProfile($profileId)['tokens_excluded'];
    }

    public function getPlaceholderImage(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['placeholder_image'];
    }

    public function getRateLimitPerHour(?string $profileId = null): int
    {
        return (int) $this->getProfile($profileId)['rate_limit'];
    }

    public function getThemeId(?string $profileId = null): string
    {
        return (string) $this->getProfile($profileId)['theme'];
    }

    public function getTimeoutSeconds(?string $profileId = null): int
    {
        return (int) $this->getProfile($profileId)['timeout'];
    }

    /**
     * The profile selected in the builder. Unknown or deleted ids always fall
     * back to the configured default instead of becoming a way to bypass the
     * profile allowlist.
     */
    public function getProfile(?string $profileId = null, bool $withApiKey = true): array
    {
        $profiles = $this->getProfiles();
        $defaultId = $this->getDefaultProfileId($profiles);
        $wanted = $profileId ?: $defaultId;

        foreach ($profiles as $profile) {
            if ($profile['id'] === $wanted) {
                if ($withApiKey) {
                    $profile['api_key'] = $this->apiKeyFor($profile['id']);
                }

                return $profile;
            }
        }

        foreach ($profiles as $profile) {
            if ($profile['id'] === $defaultId) {
                if ($withApiKey) {
                    $profile['api_key'] = $this->apiKeyFor($profile['id']);
                }

                return $profile;
            }
        }

        throw new \LogicException('At least one AI profile must always exist.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProfiles(): array
    {
        $settings = $this->getFeatureSettings();
        $raw = $settings['profiles'] ?? null;

        if (!is_array($raw) || [] === $raw) {
            return [$this->normaliseProfile('default', [
                'name' => 'Default',
            ], $settings)];
        }

        $profiles = [];
        $usedIds = [];
        foreach ($raw as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $id = trim((string) ($profile['id'] ?? ''));
            if ('' === $id && !is_numeric($key)) {
                $id = trim((string) $key);
            }
            if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/i', $id)) {
                $id = 'profile-'.(count($profiles) + 1);
            }
            while (isset($usedIds[$id])) {
                $id = 'profile-'.(count($profiles) + 1);
            }

            $usedIds[$id] = true;
            $profiles[] = $this->normaliseProfile($id, $profile, $settings);
        }

        return [] === $profiles
            ? [$this->normaliseProfile('default', ['name' => 'Default'], $settings)]
            : $profiles;
    }

    /**
     * Form fields use a textarea for excluded tokens, while runtime profiles
     * use a normalized list. Keep the two representations separate so opening
     * the settings form never feeds an array into a textarea.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProfilesForForm(): array
    {
        return array_map(static function (array $profile): array {
            $profile['tokens_excluded'] = implode("\n", (array) ($profile['tokens_excluded'] ?? []));

            return $profile;
        }, $this->getProfiles());
    }

    /** @return array<string, string> */
    public function getProfileChoices(): array
    {
        $choices = [];
        foreach ($this->getProfiles() as $profile) {
            $label = (string) $profile['name'];
            if (isset($choices[$label])) {
                $label .= ' ('.(string) $profile['id'].')';
            }
            $choices[$label] = $profile['id'];
        }

        return $choices;
    }

    public function getDefaultProfileId(?array $profiles = null): string
    {
        $profiles ??= $this->getProfiles();
        $configured = trim((string) ($this->getFeatureSettings()['default_profile'] ?? ''));

        foreach ($profiles as $profile) {
            if ($profile['id'] === $configured) {
                return $configured;
            }
        }

        return (string) $profiles[0]['id'];
    }

    /** @return array<string, string> */
    public function getFrontendProfiles(): array
    {
        $profiles = [];
        foreach ($this->getProfiles() as $profile) {
            $profiles[$profile['id']] = $profile['name'];
        }

        return $profiles;
    }

    /** @return array<string, string> */
    public function getFrontendProfileThemes(): array
    {
        $themes = [];
        foreach ($this->getProfiles() as $profile) {
            $themes[$profile['id']] = $profile['theme'];
        }

        return $themes;
    }

    /** @return array<string, string> */
    public static function providerChoices(): array
    {
        return [
            'mautic.aiemailsections.config.provider.openai'       => self::PROVIDER_OPENAI,
            'mautic.aiemailsections.config.provider.openai_cloud' => self::PROVIDER_OPENAI_CLOUD,
            'mautic.aiemailsections.config.provider.google'       => self::PROVIDER_GOOGLE,
            'mautic.aiemailsections.config.provider.deepseek'     => self::PROVIDER_DEEPSEEK,
            'mautic.aiemailsections.config.provider.qwen'         => self::PROVIDER_QWEN,
            'mautic.aiemailsections.config.provider.groq'         => self::PROVIDER_GROQ,
            'mautic.aiemailsections.config.provider.openrouter'   => self::PROVIDER_OPENROUTER,
            'mautic.aiemailsections.config.provider.anthropic'    => self::PROVIDER_ANTHROPIC,
        ];
    }

    /** @return array<string, mixed> */
    private function normaliseProfile(string $id, array $profile, array $legacy): array
    {
        $provider = (string) ($profile['provider'] ?? $legacy['provider'] ?? self::DEFAULT_PROVIDER);
        if (!isset(self::providerDefaults()[$provider])) {
            $provider = self::DEFAULT_PROVIDER;
        }

        $defaults = self::providerDefaults()[$provider];
        $baseUrl = trim((string) ($profile['base_url'] ?? $legacy['base_url'] ?? $defaults['base_url']));
        if (!in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $baseUrl = $defaults['base_url'];
        }

        $excluded = $profile['tokens_excluded'] ?? $legacy['tokens_excluded'] ?? '';
        if (is_array($excluded)) {
            $excludedTokens = array_values(array_filter(array_map('strval', $excluded)));
        } else {
            $excludedTokens = array_values(array_filter(array_map('trim', preg_split('~[\r\n,]+~', (string) $excluded) ?: [])));
        }

        return [
            'id'               => $id,
            'name'             => trim((string) ($profile['name'] ?? '')) ?: ucfirst($id),
            'provider'         => $provider,
            'base_url'         => $baseUrl,
            'model'            => trim((string) ($profile['model'] ?? $legacy['model'] ?? $defaults['model'])) ?: $defaults['model'],
            'brand'            => (string) ($profile['brand'] ?? $legacy['brand'] ?? ''),
            'tokens_enabled'   => !array_key_exists('tokens_enabled', $profile)
                ? (!array_key_exists('tokens_enabled', $legacy) || (bool) $legacy['tokens_enabled'])
                : (bool) $profile['tokens_enabled'],
            'tokens_excluded'  => $excludedTokens,
            'temperature'      => isset($profile['temperature']) && '' !== (string) $profile['temperature']
                ? (float) $profile['temperature']
                : (isset($legacy['temperature']) && '' !== (string) $legacy['temperature'] ? (float) $legacy['temperature'] : self::DEFAULT_TEMPERATURE),
            'max_tokens'       => isset($profile['max_tokens']) && '' !== (string) $profile['max_tokens']
                ? (int) $profile['max_tokens']
                : (isset($legacy['max_tokens']) && '' !== (string) $legacy['max_tokens'] ? (int) $legacy['max_tokens'] : self::DEFAULT_MAX_TOKENS),
            'placeholder_image'=> trim((string) ($profile['placeholder_image'] ?? $legacy['placeholder_image'] ?? self::DEFAULT_PLACEHOLDER)) ?: self::DEFAULT_PLACEHOLDER,
            'rate_limit'       => isset($profile['rate_limit']) && '' !== (string) $profile['rate_limit']
                ? (int) $profile['rate_limit']
                : (isset($legacy['rate_limit']) && '' !== (string) $legacy['rate_limit'] ? (int) $legacy['rate_limit'] : self::DEFAULT_RATE_LIMIT),
            'timeout'          => isset($profile['timeout']) && '' !== (string) $profile['timeout']
                ? (int) $profile['timeout']
                : (isset($legacy['timeout']) && '' !== (string) $legacy['timeout'] ? (int) $legacy['timeout'] : self::DEFAULT_TIMEOUT),
            'theme'            => trim((string) ($profile['theme'] ?? $legacy['theme'] ?? self::DEFAULT_THEME)) ?: self::DEFAULT_THEME,
        ];
    }

    private function apiKeyFor(string $profileId): string
    {
        try {
            $keys = $this->getIntegrationEntity()->getApiKeys();
        } catch (IntegrationNotFoundException) {
            return '';
        }

        if (!is_array($keys)) {
            return '';
        }

        $profileKeys = $keys['profiles'] ?? [];
        if (is_array($profileKeys) && isset($profileKeys[$profileId]) && '' !== (string) $profileKeys[$profileId]) {
            return (string) $profileKeys[$profileId];
        }

        return isset($keys['api_key']) ? (string) $keys['api_key'] : '';
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
