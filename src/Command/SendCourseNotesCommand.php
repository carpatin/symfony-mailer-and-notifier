<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'app:send-course-notes-emails',
    description: 'Sends course notes emails to interns',
)]
class SendCourseNotesCommand extends Command
{
    public function __construct(
        #[Autowire('%env(csv:APP_INTERNS_ADDRESSES)%')]
        private array $internsAddresses,
        private MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('chapter', InputArgument::REQUIRED, 'Source chapter to send notes for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // TODO: send email with attached PDFs/DOCs to all emails in the internsAddresses array
        // TODO: use MailerInterface so that the sending happens asynchronously
        // TODO: template the email as markdown for the sake of example: https://symfony.com/doc/current/mailer.html#rendering-markdown-content

        // TODO: configure a signer globally for all sent emails: https://symfony.com/doc/current/mailer.html#signing-messages-globally

        return Command::SUCCESS;
    }
}
