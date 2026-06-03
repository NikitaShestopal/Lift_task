<?php

namespace App\Massage;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CreateUserMessage
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public array $phoneNumber,
        public string $ipAddress
    ) {}
}

#[AsMessageHandler]
class CreateUserHandler
{
    public function __construct(
        private HttpClientInterface $client,
        private DocumentManagerInterface $dm
    ) {
    }

    public function __invoke(CreateUserMessage $message){
        $user = new User();
        $url = 'https://www.iplocate.io/api/lookup/' . $message->ipAddress . "/country";

        $response = $this->client->request(
            'GET',
            $url
        );

        foreach ($message->phoneNumber as $phoneNumber){
            $user->addPhone($phoneNumber);
        }

        $user->setFirstName($message->firstName);
        $user->setLastName($message->lastName);
        $user->setIpAddress($message->ipAddress);
        $user->setCountry($response->getContent());

        $this->dm->persist($user);
        $this->dm->flush();
    }
}

