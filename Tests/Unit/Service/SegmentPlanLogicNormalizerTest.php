<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Service\SegmentPlanLogicNormalizer;
use PHPUnit\Framework\TestCase;

final class SegmentPlanLogicNormalizerTest extends TestCase
{
    public function testMergesGroupsForAnAndOnlyRequest(): void
    {
        $plan = ['groups' => [
            ['conditions' => [['field' => 'firstname']]],
            ['conditions' => [['field' => 'hit_url']]],
        ]];

        $result = (new SegmentPlanLogicNormalizer())->normalize(
            'First name starts with A and opened apple.com',
            $plan
        );

        $this->assertCount(1, $result['groups']);
        $this->assertSame(['firstname', 'hit_url'], array_column($result['groups'][0]['conditions'], 'field'));
    }

    /** @dataProvider explicitOrRequests */
    public function testPreservesGroupsForExplicitOr(string $request): void
    {
        $plan = ['groups' => [
            ['conditions' => [['field' => 'firstname']]],
            ['conditions' => [['field' => 'lastname']]],
        ]];

        $result = (new SegmentPlanLogicNormalizer())->normalize($request, $plan);

        $this->assertCount(2, $result['groups']);
    }

    /** @return iterable<string, array{string}> */
    public static function explicitOrRequests(): iterable
    {
        yield 'English' => ['First name A or last name B'];
        yield 'Russian' => ['Имя A или фамилия B'];
        yield 'Serbian' => ['Ime A ili prezime B'];
    }
}
