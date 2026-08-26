<?php

declare(strict_types=1);

namespace MauticPlugin\AiEmailSectionsBundle\Service;

use Symfony\Component\HttpFoundation\Request;

final class RequestSessionReleaser
{
    public function release(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        // Generation can outlive the browser request. Persist and close the
        // session first so an interrupted provider call cannot retain its lock.
        $request->getSession()->save();
    }
}
