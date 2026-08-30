<?php

namespace App\EventSubscriber;

use App\Domain\PublicAgency\PublicAgencyRateLimits;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rate limits public B2C agency write endpoints by client IP.
 */
final class PublicAgencyRateLimitSubscriber implements EventSubscriberInterface
{
    private const string PREFIX = 'public_agency_rl_';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ('test' === $this->environment) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod()) {
            return;
        }

        $path = $request->getPathInfo();
        $limit = match (true) {
            '/api/public/agency/bookings' === $path => PublicAgencyRateLimits::BOOKING_CREATE_LIMIT,
            str_ends_with($path, '/pay') && str_starts_with($path, '/api/public/agency/bookings/') => PublicAgencyRateLimits::PAYMENT_INIT_LIMIT,
            default => null,
        };

        if (null === $limit) {
            return;
        }

        $identity = $request->getClientIp() ?: 'anonymous';
        $key = self::PREFIX . hash('xxh128', $path . '|' . $identity);

        $item = $this->cache->getItem($key);
        /** @var list<int> $hits */
        $hits = $item->isHit() ? $item->get() : [];
        if (!\is_array($hits)) {
            $hits = [];
        }

        $now = time();
        $windowStart = $now - PublicAgencyRateLimits::WINDOW_SECONDS;
        $hits = array_values(array_filter($hits, static fn (mixed $ts): bool => \is_int($ts) && $ts >= $windowStart));

        if (\count($hits) >= $limit) {
            $retryAfter = max(1, ($hits[0] + PublicAgencyRateLimits::WINDOW_SECONDS) - $now);
            throw new TooManyRequestsHttpException($retryAfter, 'Too many requests. Please retry later.');
        }

        $hits[] = $now;
        $item->set($hits);
        $item->expiresAfter(PublicAgencyRateLimits::WINDOW_SECONDS);
        $this->cache->save($item);
    }
}
