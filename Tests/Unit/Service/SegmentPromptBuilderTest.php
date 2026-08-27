<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Service\SegmentPromptBuilder;
use PHPUnit\Framework\TestCase;

final class SegmentPromptBuilderTest extends TestCase
{
    public function testIncludesPluginFieldsAndMauticGroupingRules(): void
    {
        $messages = (new SegmentPromptBuilder())->build('Android users', [[
            'object' => 'plugin_object',
            'field' => 'plugin_filter',
            'operators' => [['label' => 'equals', 'value' => '=']],
        ]]);

        $this->assertStringContainsString('Conditions inside one group are joined with AND', $messages[0]['content']);
        $this->assertStringContainsString('plugin_filter', $messages[1]['content']);
        $this->assertStringContainsString('Android users', $messages[1]['content']);
    }

    public function testLargeCatalogIsRankedByTheOperatorRequest(): void
    {
        $catalog = [];
        for ($index = 0; $index < 60; ++$index) {
            $catalog[] = [
                'object' => 'lead',
                'field' => 'unrelated_'.$index,
                'label' => 'Unrelated field '.$index,
                'operators' => [['label' => 'equals', 'value' => '=']],
            ];
        }
        $catalog[] = [
            'object' => 'company',
            'field' => 'companyname',
            'label' => 'Company Name',
            'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
        ];

        $messages = (new SegmentPromptBuilder())->build('Company names starting with A', $catalog);

        $this->assertStringContainsString('companyname', $messages[1]['content']);
        $this->assertStringNotContainsString('unrelated_59', $messages[1]['content']);
    }

    public function testOpenedSiteRanksPageHitBehaviorAheadOfCompanyWebsite(): void
    {
        $catalog = [];
        for ($index = 0; $index < 20; ++$index) {
            $catalog[] = [
                'object' => 'lead',
                'field' => 'unrelated_'.$index,
                'label' => 'Unrelated field '.$index,
                'operators' => [['label' => 'equals', 'value' => '=']],
            ];
        }
        $catalog[] = [
            'object' => 'company',
            'field' => 'companywebsite',
            'label' => 'Website',
            'operators' => [['label' => 'contains', 'value' => 'contains']],
        ];
        $catalog[] = [
            'object' => 'behaviors',
            'field' => 'hit_url',
            'label' => 'Visited specific URL',
            'operators' => [['label' => 'contains', 'value' => 'contains']],
        ];
        $catalog[] = [
            'object' => 'behaviors',
            'field' => 'url_in_last_30_days',
            'label' => 'Visited X URL in last 30 days',
            'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
        ];
        $catalog[] = [
            'object' => 'company',
            'field' => 'companyname',
            'label' => 'Company Name',
            'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
        ];
        $catalog[] = [
            'object' => 'lead',
            'field' => 'firstname',
            'label' => 'First Name',
            'operators' => [['label' => 'starts with', 'value' => 'startsWith']],
        ];

        $messages = (new SegmentPromptBuilder())->build(
            'Create a segment containing all contacts whose first name starts with A and opened the site apple.com',
            $catalog
        );

        $this->assertStringContainsString('hit_url', $messages[1]['content']);
        $this->assertStringContainsString('firstname', $messages[1]['content']);
        $this->assertStringNotContainsString('url_in_last_30_days', $messages[1]['content']);
        $this->assertStringNotContainsString('companyname', $messages[1]['content']);
        $this->assertStringNotContainsString('companywebsite', $messages[1]['content']);
        $this->assertStringContainsString('never use a company `website`/`companywebsite`', $messages[0]['content']);
    }
}
