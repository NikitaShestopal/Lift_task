<?php

namespace App\Controller;

use App\Document\User;
use App\DTO\UserDTO;
use App\Services\IdempotencyBlocker;
use App\Services\UserRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Користувачі (Users)')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly IdempotencyBlocker $idempotencyBlocker,
        private readonly UserRequestValidator $requestValidator,
        private readonly SerializerInterface $serializer
    ) {}

    #[Route('/api/users', name: 'add_user', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Створити нового користувача',
        description: 'Валідує дані, перевіряє унікальність/ідемпотентність за першим номером телефону та передає задачу в чергу повідомлень.'
    )]
    #[OA\RequestBody(
        required: true,
        description: 'JSON-payload для створення користувача',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'Олександр'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Шестопал'),
                new OA\Property(
                    property: 'phoneNumbers',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['+380501234567', '+380671234567']
                ),
                new OA\Property(property: 'ipAddress', type: 'string', example: '178.92.0.1', nullable: true)
            ]
        )
    )]
    #[OA\Response(
        response: 202,
        description: 'Запит прийнято в обробку (відправлено в Messenger Bus)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'Accepted')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Помилка валідації структури або DTO',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                    example: ['firstName' => 'This value should not be blank.']
                )
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Запит уже обробляється (спрацював Idempotency Blocker)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Запит вже обробляється.')
            ]
        )
    )]
    public function create(Request $request, MessageBusInterface $bus, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $validationErrors = $this->requestValidator->validate($data);
        if (!empty($validationErrors)) {
            return new JsonResponse(['errors' => $validationErrors], Response::HTTP_BAD_REQUEST);
        }

        $mainPhone = $data['phoneNumbers'][0];
        if (!$this->idempotencyBlocker->tryLock($mainPhone)) {
            return new JsonResponse(['error' => 'Запит вже обробляється.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $phoneNumbers = array_unique($data['phoneNumbers'] ?? []);
        $ipAddress = $request->getClientIp();
        if (!$ipAddress || $ipAddress === '127.0.0.1' || str_starts_with($ipAddress, '172.')) {
            $ipAddress = $data['ipAddress'] ?? '178.92.0.1';
        }
        $message = new UserDTO(
            $data['firstName'] ?? '',
            $data['lastName'] ?? '',
            $phoneNumbers,
            $ipAddress
        );

        $bus->dispatch($message);

        $response = $this->json(['status' => 'Accepted'], 202);
        $response->headers->set('Connection', 'close');

        return $response;
    }

    #[Route('/api/users', name: 'get_all_users', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'Отримати список користувачів',
        description: 'Повертає перелік усіх документів користувачів із бази даних MongoDB з можливістю сортування.'
    )]
    #[OA\Parameter(
        name: 'sortBy',
        in: 'query',
        description: 'Поле, за яким здійснюється сортування',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: ['firstName', 'lastName', 'phoneNumbers', 'ipAddress', 'country'],
            default: 'phoneNumbers'
        )
    )]
    #[OA\Parameter(
        name: 'direction',
        in: 'query',
        description: 'Напрямок сортування (asc — за зростанням, desc — за спаданням)',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')
    )]
    #[OA\Response(
        response: 200,
        description: 'Список користувачів успішно згенеровано',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'records',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '64a7c1e3f8d2b30012345678'),
                            new OA\Property(property: 'firstName', type: 'string', example: 'Олександр'),
                            new OA\Property(property: 'lastName', type: 'string', example: 'Шестопал'),
                            new OA\Property(property: 'ipAddress', type: 'string', example: '178.92.0.1'),
                            new OA\Property(property: 'country', type: 'string', example: 'Ukraine'),
                            new OA\Property(
                                property: 'phoneNumbers',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: ['+380501234567']
                            )
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Передано некоректне поле для сортування',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Недопустиме поле для сортування')
            ]
        )
    )]
    public function records(Request $request, DocumentManager $dm): JsonResponse
    {
        $sortBy = $request->query->get('sortBy', 'phoneNumbers');
        $direction = $request->query->get('direction', 'desc');

        $allowedSortFields = ['firstName', 'lastName', 'phoneNumbers', 'ipAddress', 'country'];
        if (!in_array($sortBy, $allowedSortFields, true)) {
            return new JsonResponse(['error' => 'Недопустиме поле для сортування'], 400);
        }

        if ($sortBy === 'phoneNumbers') {
            $sortBy = 'phoneNumbers';
        }

        $users = $dm->getRepository(User::class)->findBy([], [$sortBy => $direction]);

        $jsonData = $this->serializer->serialize(['records' => $users], 'json');

        return new JsonResponse($jsonData, 200, [], true);
    }
}
