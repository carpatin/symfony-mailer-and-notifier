<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Throwable;

#[AsCommand(
    name: 'app:notify-schedule',
    description: 'Notifies interns through SMS about schedule changes',
)]
class NotifyScheduleCommand extends Command
{
    public function __construct(
        private readonly TexterInterface $texter,
        #[Autowire('%env(csv:APP_INTERNS_PHONES)%')]
        private readonly array $internPhoneNumbers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('event-name', InputArgument::REQUIRED, 'Scheduled event name')
            ->addArgument('event-time', InputArgument::REQUIRED, 'Scheduled event time');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $eventName = $input->getArgument('event-name');
        $eventTime = $input->getArgument('event-time');

        try {
            $message = 'Hello, your event '.$eventName.' is scheduled for '.$eventTime;
            foreach ($this->internPhoneNumbers as $phoneNumber) {
                $sms = new SmsMessage($phoneNumber, $message);
                $this->texter->send($sms);
            }
        } catch (Throwable $e) {
            $io->error('Failed to send SMS notifications: '.$e->getMessage());

            return Command::FAILURE;
        }
        $io->success(
            sprintf(
                'Event notification for %s at %s sent to %s',
                $eventName,
                $eventTime,
                implode(', ', $this->internPhoneNumbers),
            ),
        );

        return Command::SUCCESS;
    }
}
