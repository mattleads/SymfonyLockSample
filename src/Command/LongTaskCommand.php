<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:long-task',
    description: 'Demonstrates a long-running task that refreshes its lock.',
)]
class LongTaskCommand extends Command
{
    public function __construct(
        #[Target('cron_jobs')]
        private readonly LockFactory $lockFactory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Use a lock with a short TTL of 5 seconds.
        $lock = $this->lockFactory->createLock('long_import_job', 5);

        $io->title('Starting long-running import job');
        $io->text('Attempting to acquire lock with a 5 second TTL...');

        if (!$lock->acquire(true)) { // Block until the lock is acquired
            $io->error('Could not acquire the lock. Another import job may be running.');
            return Command::FAILURE;
        }

        $io->success('Lock acquired. Starting processing...');

        try {
            // Simulate a large data set
            $largeDataSet = range(1, 10);
            foreach ($largeDataSet as $row) {
                $io->text(sprintf('Processing row %d...', $row));
                sleep(2); // Each row takes 2 seconds

                // The loop takes 2 seconds, but the TTL is 5 seconds.
                // Without refresh(), the lock would expire after the 3rd row.
                $io->text('Refreshing lock for another 5 seconds...');
                $lock->refresh();
                $io->comment('Lock refreshed.');
            }
        } finally {
            $io->text('Releasing lock.');
            $lock->release();
        }

        $io->success('Long-running import job finished successfully.');

        return Command::SUCCESS;
    }
}