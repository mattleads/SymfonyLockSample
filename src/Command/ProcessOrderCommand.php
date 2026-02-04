<?php

namespace App\Command;

use App\Service\OrderProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-order',
    description: 'Processes a given order, demonstrating the try-finally lock pattern.',
)]
class ProcessOrderCommand extends Command
{
    public function __construct(private readonly OrderProcessor $orderProcessor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order-id', InputArgument::OPTIONAL, 'The ID of the order to process.', 1)
            ->addOption('crash', null, InputOption::VALUE_NONE, 'Simulate a crash during processing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = (int) $input->getArgument('order-id');
        $crash = $input->getOption('crash');

        $io->title(sprintf('Processing Order #%d', $orderId));
        
        if ($crash) {
            $io->warning('Simulating a crash during processing.');
        }

        try {
            $this->orderProcessor->processOrder($orderId, $crash);
            $io->success(sprintf('Order %d processed successfully.', $orderId));
        } catch (\Exception $e) {
            $io->error(sprintf('An error occurred while processing order %d: %s', $orderId, $e->getMessage()));
            $io->note('Check your logs (var/log/dev.log) and Redis (KEYS *order_*) to see that the lock was released.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}