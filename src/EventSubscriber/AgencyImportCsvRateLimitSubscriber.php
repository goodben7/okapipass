<?php

namespace App\EventSubscriber;

use App\Domain\Agency\DeclarationCsvLimits;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Limits POST /api/agency/declarations/import-csv per authenticated partner.
 */
final class AgencyImportCsvRateLimitSubscriber implements EventSubscriberInterface
{
    private const string PATH = '/api/agency/declarations/import-csv';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod() || self::PATH !== $request->getPathInfo()) {
            return;
        }

        $user = $this->security->getUser();
        $identity = $user?->getUserIdentifier() ?? ($request->getClientIp() ?: 'anonymous');
        $key = 'agency_csv_import_rl_'.hash('xxh128', $identity);

        $item = $this->cache->getItem($key);
        /** @var list<int> $hits */
        $hits = $item->isHit() ? $item->get() : [];
        if (!\is_array($hits)) {
            $hits = [];
        }

        $now = time();
        $windowStart = $now - DeclarationCsvLimits::RATE_WINDOW_SECONDS;
        $hits = array_values(array_filter($hits, static fn (mixed $ts): bool => \is_int($ts) && $ts >= $windowStart));

        if (\count($hits) >= DeclarationCsvLimits::RATE_LIMIT) {
            $retryAfter = max(1, ($hits[0] + DeclarationCsvLimits::RATE_WINDOW_SECONDS) - $now);
            throw new TooManyRequestsHttpException($retryAfter, 'Too many CSV imports. Please retry later.');
        }

        $hits[] = $now;
        $item->set($hits);
        $item->expiresAfter(DeclarationCsvLimits::RATE_WINDOW_SECONDS);
        $this->cache->save($item);
    }
}
