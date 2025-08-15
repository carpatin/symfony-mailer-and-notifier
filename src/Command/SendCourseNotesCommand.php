<?php

declare(strict_types=1);

namespace App\Command;

use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Throwable;

#[AsCommand(
    name: 'app:send-course-notes-emails',
    description: 'Sends course notes emails to interns',
)]
class SendCourseNotesCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        #[Autowire('%env(csv:APP_INTERNS_ADDRESSES)%')]
        private readonly array $internsAddresses,
        #[Autowire('%env(csv:APP_CHAPTERS)%')]
        private readonly array $chapters,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'chapters',
                InputArgument::IS_ARRAY,
                'Source chapters to send notes for. Choose from: '.implode(', ', $this->chapters),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            // validate provided chapters
            $chapters = $input->getArgument('chapters');
            array_walk($chapters, static function ($chapter, $k, $chapters) {
                if (!in_array($chapter, $chapters, true)) {
                    throw new RuntimeException(sprintf('Invalid chapter "%s"', $chapter));
                }
            }, $this->chapters);

            $fromAddress = new Address('internship@evozon.com', 'PHP & Symfony Internship');

            // prepare the email
            $email = (new TemplatedEmail())
                ->from($fromAddress)
                ->to(...$this->internsAddresses)
                ->subject('Course notes for chapters '.implode(', ', $chapters))
                ->htmlTemplate('email/course_notes.html.twig')
                ->context([
                    'chapters' => $chapters,
                    'paths'    => $this->resolveImagePaths(),
                ]);

            foreach ($this->provideAttachmentsPaths($chapters) as $attachment) {
                $email->attachFromPath($attachment);
            }

            // Render the twig template using embedded photos, also adds text content converted from HTML
            $this->bodyRenderer->render($email);
        } catch (Throwable $e) {
            $io->error('Failed to create and render email template: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Send asynchronously via MailerInterface
        try {
            $this->mailer->send($email);
        } catch (Throwable $e) {
            $io->error('Failed to send email: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(
            sprintf(
                'Course notes email (%s) sent to %s',
                implode(', ', $chapters),
                implode(', ', $this->internsAddresses),
            ),
        );

        return Command::SUCCESS;
    }

    private function resolveImagePaths(): array
    {
        $assetsBase = $this->projectDir.'/assets/images';
        $logoImage = 'logo.jpg';
        $headerImage = 'email/header.png';
        $footerImage = 'email/footer.png';

        $pathsToValidate = [$assetsBase.'/'.$logoImage, $assetsBase.'/'.$headerImage, $assetsBase.'/'.$footerImage];
        foreach ($pathsToValidate as $file) {
            if (!is_file($file)) {
                throw new RuntimeException(sprintf('File "%s" not found', $file));
            }
        }

        return [
            'logo'   => '@images/'.$logoImage,
            'header' => '@images/'.$headerImage,
            'footer' => '@images/'.$footerImage,
        ];
    }

    private function provideAttachmentsPaths(mixed $chapters): array
    {
        $assetsBase = $this->projectDir.'/assets/course_notes';
        $attachments = [];
        foreach ($chapters as $chapter) {
            $attachments[] = $assetsBase.'/'.str_replace('-', ' ', $chapter).'.md';
        }

        return $attachments;
    }
}
