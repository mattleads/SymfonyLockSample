<?php

namespace App\EventListener;

use App\Attribute\Lock;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class LockAttributeListener
{
    private \WeakMap $locks;

    public function __construct(
        #[Target('invoice_generation')]
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->locks = new \WeakMap();
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onKernelController(ControllerEvent $event): void
    {
        $attributes = $event->getAttributes();
        if (!isset($attributes[Lock::class])) {
            return;
        }

        /** @var Lock $lockAttr */
        $lockAttr = $attributes[Lock::class][0];

        $request = $event->getRequest();
        $resource = $lockAttr->resourceName;

        // Simple interpolation for request attributes (e.g., 'invoice_{id}')
        foreach ($request->attributes->all() as $key => $value) {
            if (is_scalar($value)) {
                $resource = str_replace("{{$key}}", (string) $value, $resource);
            }
        }

        $this->logger->info(sprintf('Attempting to acquire lock for resource "%s".', $resource));

        $lock = $this->lockFactory->createLock($resource, $lockAttr->ttl);

        if (!$lock->acquire($lockAttr->blocking)) {
            $this->logger->warning(sprintf('Resource "%s" is currently locked.', $resource));
            throw new TooManyRequestsHttpException(null, 'Resource is currently locked.');
        }

        $this->logger->info(sprintf('Lock acquired for resource "%s".', $resource));

        // Store lock to release it later
        $this->locks[$event->getRequest()] = $lock;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if (isset($this->locks[$request])) {
            /** @var LockInterface $lock */
            $lock = $this->locks[$request];
            $resource = 'unknown'; // Can't easily get the resource name back from the lock object

            $this->logger->info(sprintf('Releasing lock for request to "%s".', $request->getPathInfo()));
            $lock->release();
            unset($this->locks[$request]);
        }
    }
}
