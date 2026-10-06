<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Two-factor authentication (TOTP) management: enable, backup codes, disable, admin reset.
 *
 * The login challenge itself is handled by scheb/2fa-bundle (see the "two_factor" key of the main firewall).
 */
#[Route('/users/{id}/two-factor', name: 'app_two_factor_', requirements: ['id' => '\d+'])]
final class TwoFactorController extends AbstractController
{
    private const PENDING_TOTP_SESSION_KEY = 'two_factor_pending_totp';
    private const BACKUP_CODES_SESSION_KEY = 'two_factor_backup_codes';
    private const BACKUP_CODES_COUNT = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/enable', name: 'enable', methods: ['GET', 'POST'])]
    public function enable(Request $request, User $user): Response
    {
        $this->denyAccessUnlessOwnAccount($user);

        if ($user->isTotpAuthenticationEnabled()) {
            $this->addFlash('info', 'flash.two_factor.already_enabled');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        // The TOTP seed stays in session until the user proves their app is set up with a valid code.
        $session = $request->getSession();
        $pendingTotp = $session->get(self::PENDING_TOTP_SESSION_KEY);
        if (!is_string($pendingTotp)) {
            $pendingTotp = $this->totpAuthenticator->generateSecret();
            $session->set(self::PENDING_TOTP_SESSION_KEY, $pendingTotp);
        }

        // Detached copy: the pending seed must never be flushed before confirmation.
        $candidate = clone $user;
        $candidate->setTotpSecret($pendingTotp);

        $invalidCode = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('two_factor_enable', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'flash.user.invalid_csrf');

                return $this->redirectToRoute('app_two_factor_enable', ['id' => $user->getId()]);
            }

            if ($this->totpAuthenticator->checkCode($candidate, trim((string) $request->request->get('code')))) {
                $backupCodes = $this->generateBackupCodes();

                $user->setTotpSecret($pendingTotp);
                $user->setBackupCodes($backupCodes);
                $user->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->flush();

                $session->remove(self::PENDING_TOTP_SESSION_KEY);
                // Plain backup codes are kept only until they are displayed once.
                $session->set(self::BACKUP_CODES_SESSION_KEY, $backupCodes);

                $this->logger->info('Two-factor authentication enabled', ['id' => $user->getId()]);
                $this->addFlash('success', 'flash.two_factor.enabled');

                return $this->redirectToRoute('app_two_factor_backup_codes', ['id' => $user->getId()]);
            }

            $invalidCode = true;
        }

        $qrCode = new Builder(
            writer: new SvgWriter(),
            data: $this->totpAuthenticator->getQRContent($candidate),
            size: 250,
        )->build();

        return $this->render('two_factor/enable.html.twig', [
            'user' => $user,
            'qr_code_data_uri' => $qrCode->getDataUri(),
            'manual_entry_code' => $pendingTotp,
            'invalid_code' => $invalidCode,
        ], new Response(status: $invalidCode ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/backup-codes', name: 'backup_codes', methods: ['GET'])]
    public function backupCodes(Request $request, User $user): Response
    {
        $this->denyAccessUnlessOwnAccount($user);

        // Shown once: reloading the page will not display the codes again.
        $backupCodes = $request->getSession()->remove(self::BACKUP_CODES_SESSION_KEY);
        if (!is_array($backupCodes) || $backupCodes === []) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('two_factor/backup_codes.html.twig', [
            'user' => $user,
            'backup_codes' => $backupCodes,
        ]);
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    public function disable(Request $request, User $user): Response
    {
        $this->denyAccessUnlessOwnAccount($user);

        if (!$this->isCsrfTokenValid('two_factor_disable'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.user.invalid_csrf');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $user->disableTwoFactor();
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Two-factor authentication disabled', ['id' => $user->getId()]);
        $this->addFlash('success', 'flash.two_factor.disabled');

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    /**
     * Recovery path for a user who lost both their device and their backup codes.
     */
    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('two_factor_reset'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.user.invalid_csrf');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $user->disableTwoFactor();
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Two-factor authentication reset by an administrator', ['id' => $user->getId()]);
        $this->addFlash('success', 'flash.two_factor.reset');

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    /**
     * Two-factor settings belong to the account owner only (admins can only reset them).
     */
    private function denyAccessUnlessOwnAccount(User $user): void
    {
        if ($this->getUser() !== $user) {
            throw $this->createAccessDeniedException('error.access_denied.two_factor');
        }
    }

    /**
     * @return list<string>
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; ++$i) {
            $codes[] = bin2hex(random_bytes(5));
        }

        return $codes;
    }
}
