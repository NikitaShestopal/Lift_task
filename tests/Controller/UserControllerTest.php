<?php

namespace App\Tests\Controller;

use App\Services\IdempotencyBlocker;
use App\Services\UserRequestValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class UserControllerTest extends WebTestCase
{
    private $client;
    private $idempotencyBlockerMock;
    private $requestValidatorMock;
    private $messageBusMock;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->idempotencyBlockerMock = $this->createMock(IdempotencyBlocker::class);
        $this->requestValidatorMock = $this->createMock(UserRequestValidator::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);

        $container = static::getContainer();
        $container->set(IdempotencyBlocker::class, $this->idempotencyBlockerMock);
        $container->set(UserRequestValidator::class, $this->requestValidatorMock);
        $container->set(MessageBusInterface::class, $this->messageBusMock);
    }

    public function testCreateUserSuccess(): void
    {
        $payload = [
            'firstName' => 'Олександр',
            'lastName' => 'Шестопал',
            'phoneNumbers' => ['+380501234567', '+380671234567'],
            'ipAddress' => '178.92.0.1'
        ];

        $this->requestValidatorMock->expects($this->once())
            ->method('validate')
            ->willReturn([]);

        $this->idempotencyBlockerMock->expects($this->once())
            ->method('tryLock')
            ->willReturn(true);

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertEquals(Response::HTTP_ACCEPTED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateUserValidationError(): void
    {
        $payload = ['firstName' => ''];

        $this->requestValidatorMock->expects($this->once())
            ->method('validate')
            ->willReturn(['firstName' => 'This value should not be blank.']);

        $this->client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateUserIdempotencyLocked(): void
    {
        $payload = ['firstName' => 'Test', 'phoneNumbers' => ['+380501234567']];

        $this->requestValidatorMock->expects($this->once())
            ->method('validate')
            ->willReturn([]);

        $this->idempotencyBlockerMock->expects($this->once())
            ->method('tryLock')
            ->willReturn(false);

        $this->client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $this->client->getResponse()->getStatusCode());
    }

    public function testGetUsersSuccess(): void
    {
        // Перевіряємо звичайний успішний запит (DocumentManager вже замінено фабрикою)
        $this->client->request('GET', '/api/users?sortBy=lastName&direction=asc');

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testGetUsersInvalidSortField(): void
    {
        $this->client->request('GET', '/api/users?sortBy=unallowed_field');

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }
}
