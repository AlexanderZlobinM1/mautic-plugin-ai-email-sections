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
}
