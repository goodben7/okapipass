<?php

namespace App\Command;

use App\Contract\AgencySmsSenderInterface;
use App\Service\Agency\AgencySmsRouter;
use App\Service\Agency\DreamDigitalSmsSender;
use App\Service\Agency\LoggingAgencySmsSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'okapi:test-sms',
    description: 'Send a test SMS via AgencySmsSender (Dream Digital when enabled)',
)]
final class TestSmsCommand extends Command
{
    public function __construct(
        private AgencySmsSenderInterface $smsSender,
        #[Autowire('%env(bool:default:dream_digital_sms_enabled_default:DREAM_DIGITAL_SMS_ENABLED)%')]
        private bool $dreamDigitalEnabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Phone number. Example: +243823783066')
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_OPTIONAL,
                'SMS body',
                'OkapiPass TEST SMS — si tu reçois ce message, Dream Digital fonctionne.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $phone = (string) $input->getArgument('phone');
        $message = (string) $input->getOption('message');

        $driver = match (true) {
            $this->smsSender instanceof AgencySmsRouter && $this->dreamDigitalEnabled => 'Dream Digital (live)',
            $this->smsSender instanceof DreamDigitalSmsSender => 'Dream Digital (direct)',
            $this->smsSender instanceof LoggingAgencySmsSender => 'Logging stub (DREAM_DIGITAL_SMS_ENABLED=0)',
            default => $this->smsSender::class,
        };

        $io->section('SMS test');
        $io->listing([
            'Driver: '.$driver,
            'To: '.$phone,
            'Message: '.$message,
        ]);

        try {
            $smsMessageId = $this->smsSender->send($phone, $message);
            $io->success(sprintf('SMS accepted by gateway. smsMessageId=%s', $smsMessageId));
            $io->note('Check the phone inbox. If stub driver: look at app logs only.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
