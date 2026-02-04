<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:blocking-test',
    description: 'Demonstrates blocking vs. non-blocking lock acquisition.',
)]
class BlockingTestCommand extends Command
{
    private const LOCK_RESOURCE = 'demo_blocking_lock';

    public function __construct(
        #[Target('cron_jobs')]
        private readonly LockFactory $lockFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hold', null, InputOption::VALUE_NONE, 'Acquire the lock and hold it for 10 seconds.')
            ->addOption('non-blocking', null, InputOption::VALUE_NONE, 'Attempt to acquire the lock without waiting.')
            ->addOption('blocking', null, InputOption::VALUE_NONE, 'Attempt to acquire the lock and wait for it.')
            ->addOption('retry', null, InputOption::VALUE_NONE, 'Attempt to acquire the lock with a retry loop.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE, 15);

        if ($input->getOption('hold')) {
            $io->title('Mode: Hold');
            $io->text('Attempting to acquire lock...');
            if ($lock->acquire(true)) {
                $io->success('Lock acquired. Holding for 10 seconds...');
                sleep(10);
                $io->text('Releasing lock.');
                $lock->release();
                $io->success('Done.');
            } else {
                $io->error('Could not acquire lock.');
                return Command::FAILURE;
            }
        } elseif ($input->getOption('non-blocking')) {
            $io->title('Mode: Non-Blocking');
            $io->text('Attempting to acquire lock...');
            if ($lock->acquire()) {
                $io->success('Lock acquired immediately!');
                $lock->release();
            } else {
                $io->warning('Could not acquire lock. The resource is busy.');
            }
        } elseif ($input->getOption('blocking')) {
            $io->title('Mode: Blocking');
            $io->text('Attempting to acquire lock... This will wait indefinitely.');
            if ($lock->acquire(true)) {
                $io->success('Lock acquired after waiting!');
                $lock->release();
            } else {
                // This part is theoretically unreachable with acquire(true) unless something goes wrong with the store
                $io->error('Could not acquire lock.');
                return Command::FAILURE;
            }
        } elseif ($input->getOption('retry')) {
            $io->title('Mode: Retry Loop');
            $maxRetries = 5;
            $retryCount = 0;
            $io->text("Attempting to acquire lock, retrying up to {$maxRetries} times...");

            while (!$lock->acquire()) {
                if ($retryCount++ >= $maxRetries) {
                    $io->error("Could not acquire lock after {$maxRetries} attempts.");
                    return Command::FAILURE;
                }
                $io->text("Attempt #{$retryCount} failed. Waiting 1 second...");
                sleep(1);
            }
            $io->success("Lock acquired after {$retryCount} retries.");
            $lock->release();
        } else {
            $io->warning('You must choose a mode. Use --hold, --non-blocking, --blocking, or --retry.');
            return Command::INVALID;
        }

        return Command::SUCCESS;
    }
}