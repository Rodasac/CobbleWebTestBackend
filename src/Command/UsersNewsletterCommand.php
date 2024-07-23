<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:users:newsletter',
    description: 'Add a short description for your command',
)]
class UsersNewsletterCommand extends Command
{

    private MailerInterface $mailer;
    private UserRepository $userRepository;

    public function __construct(MailerInterface $mailer, UserRepository $userRepository)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->userRepository = $userRepository;
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending newsletter to users created the last week');

        $users = $this->userRepository->findAllActiveCreatedTheLastWeek();

        $email = (new Email())
            ->from(new Address('admin@cobbleweb.example', 'CobbleWeb'))
            ->to('admin@cobbleweb.example')
            ->bcc(...array_map(fn ($user) => $user->getEmail(), $users))
            ->subject('Your best newsletter')
            ->text('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec id interdum nibh. Phasellus blandit tortor in cursus convallis. Praesent et tellus fermentum, pellentesque lectus at, tincidunt risus. Quisque in nisl malesuada, aliquet nibh at, molestie libero.');

        $this->mailer->send($email);

        $io->success('Newsletter sent successfully');

        return Command::SUCCESS;
    }
}
