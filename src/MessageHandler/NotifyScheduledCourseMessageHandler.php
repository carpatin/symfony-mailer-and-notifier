<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotifyScheduledCourseMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

#[AsMessageHandler]
final readonly class NotifyScheduledCourseMessageHandler
{
    public function __construct(
        private TexterInterface $texter,
        private LoggerInterface $logger,
        #[Autowire('%env(csv:APP_INTERNS_PHONES)%')]
        private array $internPhoneNumbers,
    ) {}

    /**
     * @throws TransportExceptionInterface
     */
    public function __invoke(NotifyScheduledCourseMessage $message): void
    {
        foreach ($this->internPhoneNumbers as $phoneNumber) {
            $sms = new SmsMessage($phoneNumber, $message->reminder);
            $this->texter->send($sms);
        }

        $this->logger->info(
            sprintf(
                'Reminder notification for course "%s" scheduled for "%s" sent to %s',
                $message->courseName,
                $message->courseDateTime->format('l, j F Y H:i:s'),
                implode(', ', $this->internPhoneNumbers),
            ),
        );
    }
}
