<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TemplateController extends AbstractController
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'app_template')]
    public function index(): Response
    {
        $this->logger->info('app_template - start');

        // todo

        $this->logger->info('app_template - end');

        return $this->render('template/index.html.twig', [
            'controller_name' => 'TemplateController',
        ]);
    }
}
