<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;

readonly class OrderProcessor
{
    public function __construct(
        #[Target('order_processing')]
        private LockFactory     $lockFactory,
        private LoggerInterface $logger,
    ) {}

    public function processOrder(int $orderId, bool $crash = false): void
    {
        // The resource name should be unique for each order.
        $lock = $this->lockFactory->createLock('order_' . $orderId, 30);

        $this->logger->info(sprintf('Attempting to acquire lock for order %d.', $orderId));

        if (!$lock->acquire()) {
            // Fail fast if another process is already handling this order.
            $this->logger->warning(sprintf('Order %d is already being processed.', $orderId));
            throw new \RuntimeException(sprintf('Order %d is already being processed.', $orderId));
        }

        $this->logger->info(sprintf('Lock acquired for order %d.', $orderId));

        try {
            // CRITICAL SECTION
            // This is where you would perform payment capture, inventory updates, etc.
            $this->logger->info(sprintf('Processing order %d. This will take a few seconds.', $orderId));
            sleep(5); // Simulate work

            if ($crash) {
                $this->logger->error(sprintf('Simulating a crash while processing order %d.', $orderId));
                throw new \Exception('Something went wrong! The payment gateway is down.');
            }

            $this->chargeUser($orderId);
            $this->logger->info(sprintf('Finished processing order %d.', $orderId));

        } finally {
            $this->logger->info(sprintf('Releasing lock for order %d.', $orderId));
            $lock->release();
        }
    }

    private function chargeUser(int $id): void
    {
        // In a real application, this would interact with a payment service.
        $this->logger->info(sprintf('Charging user for order %d.', $id));
        // ... payment logic
    }
}
