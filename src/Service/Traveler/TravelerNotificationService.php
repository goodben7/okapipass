<?php

namespace App\Service\Traveler;

use App\Contract\AgencySmsSenderInterface;

final class TravelerNotificationService
{
    public function __construct(private AgencySmsSenderInterface $smsSender)
    {
    }

    public function notifyPaymentPaid(string $phone, string $ref): string
    {
        $message = sprintf(
            'OkapiPass: paiement confirme pour le billet %s. Merci de votre confiance.',
            strtoupper(trim($ref)),
        );

        return $this->smsSender->send($phone, $message);
    }

    public function notifyLowWallet(string $phone, int $balance): string
    {
        $message = sprintf(
            'OkapiPass: solde wallet faible (%d CDF). Rechargez pour eviter les interruptions.',
            $balance,
        );

        return $this->smsSender->send($phone, $message);
    }

    public function notifyDepartureReminder(
        string $phone,
        string $origin,
        string $dest,
        string $date,
        string $time,
    ): string {
        $message = sprintf(
            'OkapiPass rappel: depart %s -> %s le %s a %s. Presentez-vous a l\'heure avec votre billet.',
            $origin,
            $dest,
            $date,
            $time,
        );

        return $this->smsSender->send($phone, $message);
    }
}
