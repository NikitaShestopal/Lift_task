<?php

namespace App\Services;

use App\Document\User;
use App\DTO\UserDTO;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateUserHandler
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly IpLocateClient $ipLocateClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(UserDTO $message): void
    {
        try {
            $existingUser = $this->dm->getRepository(User::class)->findOneBy([
                'phoneNumbers' => ['$in' => $message->phoneNumbers]
            ]);

            if ($existingUser !== null) {
                $this->logger->warning(sprintf('Дублікат в MongoDB! Телефони: %s', implode(', ', $message->phoneNumbers)));
                return;
            }

            $user = new User();
            foreach ($message->phoneNumbers as $phoneNumber) {
                $user->addPhone($phoneNumber);
            }

            $country = $this->ipLocateClient->getCountryByIp($message->ipAddress);

            $user->setFirstName($message->firstName);
            $user->setLastName($message->lastName);
            $user->setIpAddress($message->ipAddress);
            $user->setCountry($country);

            $this->dm->persist($user);
            $this->dm->flush();

        } catch (\Exception $exception) {
            if (str_contains($exception->getMessage(), 'E11000')) {
                $this->logger->warning(sprintf('Конфлікт унікальності в MongoDB: %s', $exception->getMessage()));
                return;
            }

            $this->logger->error(sprintf('Помилка при створенні користувача: %s', $exception->getMessage()));
            throw $exception;
        }
    }
}
