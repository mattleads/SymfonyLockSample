<?php

namespace App\Controller;

use App\Attribute\Lock;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    #[Route('/invoice/{id}/generate', name: 'invoice_generate', methods: ['GET'])]
    #[Lock(resourceName: 'invoice_{id}', ttl: 60)]
    public function generate(int $id, LoggerInterface $logger): Response
    {
        $logger->info(sprintf('Lock acquired for invoice %d. Starting heavy generation logic.', $id));

        // This code executes ONLY if the lock is acquired
        // Simulate a long process
        sleep(10);

        $logger->info(sprintf('Finished generating invoice %d.', $id));
        
        return new Response(sprintf('Invoice %d generated successfully! The lock has been released.', $id));
    }
}
