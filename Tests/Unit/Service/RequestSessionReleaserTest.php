<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Tests\Unit\Service;

use MauticPlugin\AiEmailSectionsBundle\Service\RequestSessionReleaser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class RequestSessionReleaserTest extends TestCase
{
    public function testPersistsAndClosesAnAttachedSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('save');

        $request = new Request();
        $request->setSession($session);

        (new RequestSessionReleaser())->release($request);
    }

    public function testRequestWithoutSessionNeedsNoAction(): void
    {
        $request = new Request();

        (new RequestSessionReleaser())->release($request);

        self::assertFalse($request->hasSession());
    }
}
