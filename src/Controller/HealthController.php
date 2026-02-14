<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'app' => true, // App is running if we reach here
        ];

        $isHealthy = !in_array(false, $checks, true);

        return new JsonResponse([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ], $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
