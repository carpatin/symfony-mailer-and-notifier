<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\NotifyScheduledCourseMessage;
use DateMalformedStringException;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Throwable;

#[AsCommand(
    name: 'app:notify-scheduled-course',
    description: 'Notifies interns through SMS about scheduled course',
)]
class NotifyScheduledCourseCommand extends Command
{
    public function __construct(
        private readonly TexterInterface $texter,
        private readonly MessageBusInterface $bus,
        #[Autowire('%env(csv:APP_INTERNS_PHONES)%')]
        private readonly array $internPhoneNumbers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('course-name', InputArgument::REQUIRED, 'Scheduled course name')
            ->addArgument('course-time', InputArgument::REQUIRED, 'Scheduled course time');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $courseName = $input->getArgument('course-name');
        $courseTime = $input->getArgument('course-time');

        try {
            $this->scheduleReminders($courseName, $courseTime, $io);
            $this->sendImmediateNotification($courseName, $courseTime, $io);
        } catch (Throwable $e) {
            $io->error('Failed notifying scheduled course: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @throws DateMalformedStringException
     * @throws ExceptionInterface
     */
    private function scheduleReminders(mixed $courseName, string $courseStartTime, SymfonyStyle $io): void
    {
        $courseDateTime = new DateTimeImmutable($courseStartTime);
        $tenMinBeforeDateTime = $courseDateTime->modify('-10 minutes');
        $now = new DateTimeImmutable();
        $payload = sprintf('Course "%s" starts in 10 minutes!', $courseName);

        $delayMs = max(0, ($tenMinBeforeDateTime->getTimestamp() - $now->getTimestamp()) * 1000);

        $this->bus->dispatch(
            new NotifyScheduledCourseMessage($payload, $courseName, $courseDateTime),
            [new DelayStamp($delayMs)],
        );

        $io->info(
            sprintf(
                'The "10 minutes before" reminder scheduled for %s (%d ms from now)',
                $tenMinBeforeDateTime->format('l, j F Y H:i:s'),
                $delayMs,
            ),
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws DateMalformedStringException
     */
    private function sendImmediateNotification(mixed $courseName, mixed $courseTime, SymfonyStyle $io): void
    {
        $courseDateTime = new DateTimeImmutable($courseTime);
        $message = sprintf('Course "%s" is scheduled for %s!', $courseName, $courseDateTime->format('l, j F Y H:i:s'));
        foreach ($this->internPhoneNumbers as $phoneNumber) {
            $sms = new SmsMessage($phoneNumber, $message);
            $this->texter->send($sms);
        }

        $io->info(
            sprintf(
                'Immediate notification for course "%s" starting at "%s" sent to %s',
                $courseName,
                $courseDateTime->format('l, j F Y H:i:s'),
                implode(', ', $this->internPhoneNumbers),
            ),
        );
    }
}
