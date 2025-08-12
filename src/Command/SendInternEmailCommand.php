<?php

declare(strict_types=1);

namespace App\Command;

use LogicException;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Throwable;

#[AsCommand(
    name: 'app:send-intern-email',
    description: 'Sends communicational email individually to interns',
)]
class SendInternEmailCommand extends Command
{
    private const string OCCASION_WELCOME  = 'welcome';
    private const string OCCASION_FEEDBACK = 'feedback';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly BodyRendererInterface $bodyRenderer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Intern email address')
            ->addArgument('receiver', InputArgument::REQUIRED, 'Intern name')
            ->addOption(
                'occasion',
                'o',
                InputOption::VALUE_REQUIRED,
                'Intern email occasion',
                self::OCCASION_WELCOME,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse input and prepare template and images
        try {
            $fromAddress = new Address('internship@evozon.com', 'PHP & Symfony Internship');
            [$to, $receiver, $occasion] = $this->parseInput($input);
            $subject = $this->composeSubject($occasion);
            ['template_images' => $templateImages, 'cid_images' => $cidImages] = $this->resolveImagePaths($occasion);
            $htmlTemplate = match ($occasion) {
                self::OCCASION_WELCOME => 'email/welcome.html.twig',
                self::OCCASION_FEEDBACK => 'email/feedback.html.twig',
                default => throw new LogicException('No template for this occasion')
            };
        } catch (Throwable $e) {
            $io->error('Failed to parse input and prepare email data: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            // Prepare unique names for embedded CIDs
            $cids = [];
            foreach ($cidImages as $imageIdentifier => $absolutePath) {
                $cids[$imageIdentifier] = $imageIdentifier.'_image_'.md5($absolutePath.uniqid('', true)).'.png';
            }

            // Build the email
            $email = (new TemplatedEmail())
                ->from($fromAddress)
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($htmlTemplate) // expecting symfony to use league/html-to-markdown for text variant
                ->context([
                    'receiver' => $receiver,
                    // set paths for use with email.image() in the template
                    'paths'    => $templateImages,
                    // expose content IDs for inline embedded images
                    'cids'     => $cids,
                    'occasion' => $occasion,
                ]);

            // Embed header/footer images inline (CID)
            foreach ($cidImages as $imageIdentifier => $absolutePath) {
                $email->embedFromPath($absolutePath, $email->getContext()['cids'][$imageIdentifier], 'image/png');
            }

            // Render the twig templates using embedded photos, also adds text content converted from HTML
            $this->bodyRenderer->render($email);
        } catch (Throwable $e) {
            $io->error('Failed to create and render email template: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Send synchronously via TransportInterface
        try {
            $this->transport->send($email);
        } catch (Throwable $e) {
            $io->error('Failed to send email: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Intern comm. email (%s) sent to %s', $occasion, $to));

        return Command::SUCCESS;
    }

    private function resolveImagePaths(mixed $occasion): array
    {
        $assetsBase = $this->projectDir.'/assets/images';
        $logoImage = 'logo.jpg';
        $logoPath = $assetsBase.'/'.$logoImage;
        $mainImage = $occasion === self::OCCASION_WELCOME ? 'email/main_welcome.jpg' : 'email/main_feedback.jpg';
        $mainPath = $assetsBase.'/'.$mainImage;
        $footerPath = $assetsBase.'/email/footer.png';
        $headerPath = $assetsBase.'/email/header.png';

        foreach ([$logoPath, $mainPath, $footerPath, $headerPath] as $file) {
            if (!is_file($file)) {
                throw new RuntimeException(sprintf('File "%s" not found', $file));
            }
        }

        return [
            'template_images' => [
                'logo' => '@images/'.$logoImage,
                'main' => '@images/'.$mainImage,
            ],
            'cid_images'      => [
                'header' => $headerPath,
                'footer' => $footerPath,
            ],
        ];
    }

    private function parseInput(InputInterface $input): array
    {
        $to = (string)$input->getArgument('email');
        $receiver = (string)$input->getArgument('receiver');
        $occasion = (string)$input->getOption('occasion');

        if (!in_array($occasion, [self::OCCASION_WELCOME, self::OCCASION_FEEDBACK], true)) {
            throw new RuntimeException(sprintf('Invalid occasion "%s"', $occasion));
        }

        return [$to, $receiver, $occasion];
    }

    private function composeSubject(string $occasion): string
    {
        return match ($occasion) {
            self::OCCASION_WELCOME => 'Welcome to the PHP & Symfony Internship 2025',
            self::OCCASION_FEEDBACK => 'You\'ve got feedback from your trainer',
        };
    }
}
