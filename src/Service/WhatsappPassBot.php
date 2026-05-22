<?php

namespace App\Service;

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Payment;
use App\Manager\TicketManager;
use App\Message\CheckPaymentStatusMessage;
use App\Model\NewTicketModel;
use App\Repository\PaymentRepository;
use App\Repository\CheckpointRepository;
use App\Repository\GoPassRepository;
use App\Repository\ProvinceRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class WhatsappPassBot
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private TicketManager $tickets,
        private PaymentRepository $payments,
        private GoPassRepository $goPasses,
        private CheckpointRepository $checkpoints,
        private ProvinceRepository $provinces,
        private MessageBusInterface $bus,
    ) {
    }

    public function handleIncoming(string $fromPhone, string $text, string $baseUrl): string
    {
        $text = trim($text);
        $normalized = mb_strtolower($text);

        if (in_array($normalized, ['menu', 'start', 'bonjour', 'salut', 'hi', 'hello'], true)) {
            $this->reset($fromPhone);
            return $this->menu();
        }

        if (in_array($normalized, ['annuler', 'cancel', 'stop'], true)) {
            $this->reset($fromPhone);
            return "Opération annulée.\n\n" . $this->menu();
        }

        if (in_array($normalized, ['aide', 'help', '?'], true)) {
            return $this->help();
        }

        $state = $this->getState($fromPhone);
        $step = (string) ($state['step'] ?? 'MENU');
        $data = is_array($state['data'] ?? null) ? $state['data'] : [];

        if ($step === 'MENU') {
            if (in_array($normalized, ['1', 'acheter', 'acheter pass', 'pass', 'payer pass', 'passe'], true)) {
                $state = ['step' => 'ASK_NAME', 'data' => []];
                $this->setState($fromPhone, $state);
                return "Quel est votre nom complet ?";
            }

            return $this->menu();
        }

        if ($step === 'ASK_NAME') {
            $displayName = trim($text);
            if ($displayName === '') {
                return "Quel est votre nom complet ?";
            }

            $data['displayName'] = $displayName;

            $provinceOptions = $this->buildProvinceOptions();
            if ($provinceOptions === []) {
                $this->reset($fromPhone);
                return "Aucune province active trouvée.\n\n" . $this->menu();
            }

            $data['provinceOptions'] = $provinceOptions;
            $state = ['step' => 'DEPARTURE_PROVINCE_PICK', 'data' => $data];
            $this->setState($fromPhone, $state);

            return $this->formatProvinceList('Choisis la province de départ :', $provinceOptions);
        }

        if ($step === 'DEPARTURE_PROVINCE_PICK') {
            $options = is_array($data['provinceOptions'] ?? null) ? $data['provinceOptions'] : [];
            $picked = $options[trim($text)] ?? null;
            $pickedId = is_array($picked) ? (string) ($picked['id'] ?? '') : '';
            if ($pickedId === '') {
                return "Choisis un numéro de la liste (ex: 1).";
            }

            $data['departureProvinceId'] = $pickedId;

            $checkpointOptions = $this->buildCheckpointOptionsByProvinceId($pickedId);
            if ($checkpointOptions === []) {
                $data['provinceOptions'] = $this->buildProvinceOptions();
                $this->setState($fromPhone, ['step' => 'DEPARTURE_PROVINCE_PICK', 'data' => $data]);
                return "Aucun checkpoint actif dans cette province. Choisis une autre province.";
            }

            $data['checkpointOptions'] = $checkpointOptions;
            $this->setState($fromPhone, ['step' => 'DEPARTURE_CHECKPOINT_PICK', 'data' => $data]);

            return $this->formatCheckpointList('Choisis la ville de départ :', $checkpointOptions);
        }

        if ($step === 'DEPARTURE_CHECKPOINT_PICK') {
            $options = is_array($data['checkpointOptions'] ?? null) ? $data['checkpointOptions'] : [];
            $picked = $options[trim($text)] ?? null;
            $pickedId = is_array($picked) ? (string) ($picked['id'] ?? '') : '';
            if ($pickedId === '') {
                return "Choisis un numéro de la liste (ex: 1).";
            }

            $data['departureId'] = $pickedId;

            $provinceOptions = $this->buildProvinceOptions();
            if ($provinceOptions === []) {
                $this->reset($fromPhone);
                return "Aucune province active trouvée.\n\n" . $this->menu();
            }

            $data['provinceOptions'] = $provinceOptions;
            unset($data['checkpointOptions']);
            $this->setState($fromPhone, ['step' => 'ARRIVAL_PROVINCE_PICK', 'data' => $data]);

            return $this->formatProvinceList("Choisis la province d'arrivée :", $provinceOptions);
        }

        if ($step === 'ARRIVAL_PROVINCE_PICK') {
            $options = is_array($data['provinceOptions'] ?? null) ? $data['provinceOptions'] : [];
            $picked = $options[trim($text)] ?? null;
            $pickedId = is_array($picked) ? (string) ($picked['id'] ?? '') : '';
            if ($pickedId === '') {
                return "Choisis un numéro de la liste (ex: 1).";
            }

            $data['arrivalProvinceId'] = $pickedId;

            $checkpointOptions = $this->buildCheckpointOptionsByProvinceId($pickedId);
            if ($checkpointOptions === []) {
                $data['provinceOptions'] = $this->buildProvinceOptions();
                $this->setState($fromPhone, ['step' => 'ARRIVAL_PROVINCE_PICK', 'data' => $data]);
                return "Aucun checkpoint actif dans cette province. Choisis une autre province.";
            }

            $data['checkpointOptions'] = $checkpointOptions;
            $this->setState($fromPhone, ['step' => 'ARRIVAL_CHECKPOINT_PICK', 'data' => $data]);

            return $this->formatCheckpointList("Choisis la ville d'arrivée :", $checkpointOptions);
        }

        if ($step === 'ARRIVAL_CHECKPOINT_PICK') {
            $options = is_array($data['checkpointOptions'] ?? null) ? $data['checkpointOptions'] : [];
            $picked = $options[trim($text)] ?? null;
            $pickedId = is_array($picked) ? (string) ($picked['id'] ?? '') : '';
            if ($pickedId === '') {
                return "Choisis un numéro de la liste (ex: 1).";
            }

            $data['arrivalId'] = $pickedId;
            unset($data['checkpointOptions']);

            $this->setState($fromPhone, ['step' => 'BUY_GOPASS', 'data' => $data]);
            return $this->promptGoPassRoutier();
        }

        if ($step === 'BUY_GOPASS') {
            $goPass = $this->goPasses->findOneBy(['code' => $text]);
            if (!$goPass instanceof GoPass) {
                $routier = $this->goPasses->findActiveRoutier(10);
                $picked = $this->pickByNumber($text, $routier);
                if ($picked instanceof GoPass) {
                    $goPass = $picked;
                }
            }

            if (!$goPass instanceof GoPass) {
                return "Je ne reconnais pas ce pass. Réponds avec le numéro de la liste (ex: 1).";
            }

            $data['goPassId'] = (string) $goPass->getId();
            $state = ['step' => 'BUY_METHOD', 'data' => $data];
            $this->setState($fromPhone, $state);
            return "Mode de paiement ?\n1) Carte (Visa/Mastercard)\n2) Mobile Money";
        }

        if ($step === 'BUY_METHOD') {
            $choice = trim($normalized);
            $method = null;
            if (in_array($choice, ['1', 'carte', 'card'], true)) {
                $method = Payment::METHOD_CARD;
            } elseif (in_array($choice, ['2', 'mobile', 'mobile money', 'momo', 'mm'], true)) {
                $method = Payment::METHOD_MOBILE_MONEY;
            }

            if ($method === null) {
                return "Choisis 1 (Carte) ou 2 (Mobile Money).";
            }

            if ($method === Payment::METHOD_MOBILE_MONEY) {
                $data['method'] = $method;
                $this->setState($fromPhone, ['step' => 'BUY_MOMO_PHONE', 'data' => $data]);
                return "Tape le numéro Mobile Money à débiter (ex: 243XXXXXXXXX).";
            }

            $result = $this->createTicketAndPayment(
                fromPhone: $fromPhone,
                displayName: (string) ($data['displayName'] ?? ''),
                departureId: (string) ($data['departureId'] ?? ''),
                arrivalId: (string) ($data['arrivalId'] ?? ''),
                goPassId: (string) ($data['goPassId'] ?? ''),
                payerPhone: $fromPhone,
                method: $method,
                baseUrl: $baseUrl,
            );

            $this->reset($fromPhone);
            return (string) ($result['message'] ?? '');
        }

        if ($step === 'BUY_MOMO_PHONE') {
            $payerPhone = preg_replace('/[^\d+]/', '', $text) ?? '';
            $payerPhone = trim($payerPhone);
            if ($payerPhone === '') {
                return "Tape le numéro Mobile Money à débiter (ex: 243XXXXXXXXX).";
            }
            if ($payerPhone[0] !== '+') {
                $payerPhone = '+' . $payerPhone;
            }

            $result = $this->createTicketAndPayment(
                fromPhone: $fromPhone,
                displayName: (string) ($data['displayName'] ?? ''),
                departureId: (string) ($data['departureId'] ?? ''),
                arrivalId: (string) ($data['arrivalId'] ?? ''),
                goPassId: (string) ($data['goPassId'] ?? ''),
                payerPhone: $payerPhone,
                method: Payment::METHOD_MOBILE_MONEY,
                baseUrl: $baseUrl,
            );

            $paymentId = (string) ($result['paymentId'] ?? '');
            if ($paymentId !== '') {
                $this->bus->dispatch(
                    new CheckPaymentStatusMessage($paymentId, $fromPhone, 1),
                    [new DelayStamp(20000)]
                );
            }

            $this->reset($fromPhone);
            return (string) ($result['message'] ?? '');
        }

        $this->reset($fromPhone);
        return $this->menu();
    }

    private function menu(): string
    {
        return "Bienvenue sur OkapiPass.\n1) Acheter un pass routier\n\nTape 1 pour commencer, ou AIDE.";
    }

    private function help(): string
    {
        return "Commandes:\n- MENU: afficher le menu\n- ANNULER: annuler le parcours\n\nPour acheter un pass routier: tape 1 puis suis les étapes.";
    }

    private function promptGoPassRoutier(): string
    {
        $items = $this->goPasses->findActiveRoutier(10);
        if ($items === []) {
            return "Aucun GoPass ROUTIER actif trouvé. Contacte le support.";
        }

        $lines = ["Choisis un pass routier (réponds avec le numéro):"];
        foreach ($items as $i => $gp) {
            $idx = $i + 1;
            $price = number_format((float) ($gp->getPrice() ?? 0), 2, '.', '');
            $currency = (string) ($gp->getCurrency() ?? '');
            $lines[] = "{$idx}) {$gp->getLabel()} — {$price} {$currency}";
        }

        return implode("\n", $lines);
    }

    private function buildProvinceOptions(): array
    {
        $items = $this->provinces->findActive(40);
        $options = [];
        foreach ($items as $i => $p) {
            $idx = $i + 1;
            $options[(string) $idx] = [
                'id' => (string) $p->getId(),
                'label' => (string) $p->getLabel(),
            ];
        }

        return $options;
    }

    private function formatProvinceList(string $title, array $options): string
    {
        $lines = [$title];
        foreach ($options as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = (string) ($row['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $lines[] = "{$idx}) {$label}";
        }
        return implode("\n", $lines);
    }

    private function buildCheckpointOptionsByProvinceId(string $provinceId): array
    {
        $items = $this->checkpoints->findActiveByProvinceId($provinceId, 80);
        $options = [];
        foreach ($items as $i => $c) {
            $idx = $i + 1;
            $options[(string) $idx] = [
                'id' => (string) $c->getId(),
                'label' => (string) $c->getLabel(),
            ];
        }
        return $options;
    }

    private function formatCheckpointList(string $title, array $options): string
    {
        $lines = [$title];
        foreach ($options as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = (string) ($row['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $lines[] = "{$idx}) {$label}";
        }

        return implode("\n", $lines);
    }

    private function createTicketAndPayment(
        string $fromPhone,
        string $displayName,
        string $departureId,
        string $arrivalId,
        string $goPassId,
        string $payerPhone,
        string $method,
        string $baseUrl,
    ): array
    {
        $departure = $this->checkpoints->find($departureId);
        $arrival = $this->checkpoints->find($arrivalId);
        $goPass = $this->goPasses->find($goPassId);

        if (!$departure instanceof Checkpoint || !$arrival instanceof Checkpoint || !$goPass instanceof GoPass) {
            return ['message' => "Je n'arrive pas à préparer la demande. Tape MENU et réessaye."];
        }

        $phoneForTicket = $method === Payment::METHOD_MOBILE_MONEY ? $payerPhone : $fromPhone;

        $ticket = $this->tickets->createFrom(new NewTicketModel(
            displayName: $displayName !== '' ? $displayName : null,
            phone: $phoneForTicket,
            identifier: preg_replace('/\D+/', '', $phoneForTicket) ?: $phoneForTicket,
            goPass: $goPass,
            departure: $departure,
            arrival: $arrival,
            method: $method,
        ));

        $payment = $this->payments->findOneBy(['ticket' => $ticket]);

        $amount = number_format((float) ($goPass->getPrice() ?? 0), 2, '.', '');
        $currency = (string) ($goPass->getCurrency() ?? '');

        $lines = [];
        if ($displayName !== '') {
            $lines[] = "Nom: {$displayName}";
        }
        $lines[] = "Pass: {$goPass->getLabel()}";
        $lines[] = "Trajet: {$departure->getLabel()} → {$arrival->getLabel()}";
        $lines[] = "Montant: {$amount} {$currency}";

        if ($payment instanceof Payment) {
            $lines[] = "Référence paiement: {$payment->getReference()}";
        }

        if ($payment instanceof Payment && $method === Payment::METHOD_CARD) {
            $paymentId = (string) ($payment->getId() ?? '');
            if ($paymentId !== '') {
                $lines[] = '';
                $lines[] = 'Lien paiement carte: ' . rtrim($baseUrl, '/') . '/api/payments/' . $paymentId . '/card/form';
            }
        } elseif ($payment instanceof Payment && $method === Payment::METHOD_MOBILE_MONEY) {
            $lines[] = '';
            $lines[] = "Un prompt Mobile Money va s'afficher sur {$phoneForTicket}. Confirme sur ton téléphone.";
            $lines[] = "Après confirmation, tu recevras automatiquement un message de confirmation.";
        }

        $lines[] = '';
        $lines[] = "Tape MENU pour recommencer.";

        return [
            'message' => implode("\n", $lines),
            'ticketId' => (string) ($ticket->getId() ?? ''),
            'paymentId' => (string) ($payment?->getId() ?? ''),
        ];
    }

    private function pickByNumber(string $text, array $items): mixed
    {
        $n = (int) trim($text);
        if ($n < 1) {
            return null;
        }
        $idx = $n - 1;
        return $items[$idx] ?? null;
    }

    private function getState(string $phone): array
    {
        $item = $this->cache->getItem($this->stateKey($phone));
        $value = $item->get();
        return is_array($value) ? $value : ['step' => 'MENU', 'data' => []];
    }

    private function setState(string $phone, array $state): void
    {
        $item = $this->cache->getItem($this->stateKey($phone));
        $item->expiresAfter(1800);
        $item->set($state);
        $this->cache->save($item);
    }

    private function reset(string $phone): void
    {
        $this->cache->deleteItem($this->stateKey($phone));
    }

    private function stateKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
        return 'okapipass.whatsapp.bot.' . $digits;
    }
}
