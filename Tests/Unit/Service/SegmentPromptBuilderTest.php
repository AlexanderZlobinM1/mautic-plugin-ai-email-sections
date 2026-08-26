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
}
