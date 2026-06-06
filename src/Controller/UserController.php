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

class UserController extends AbstractController
{
    public function __construct(
        private readonly IdempotencyBlocker $idempotencyBlocker,
        private readonly UserRequestValidator $requestValidator,
        private readonly SerializerInterface $serializer
    ) {}

    #[Route('/api/users', name: 'add_user', methods: ['POST'])]
    public function create(Request $request, MessageBusInterface $bus, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $validationErrors = $this->requestValidator->validate($data);
        if (!empty($validationErrors)) {
            return new JsonResponse(['errors' => $validationErrors], Response::HTTP_BAD_REQUEST);
        }

        $mainPhone = $data['phoneNumbers'][0] ?? null;
        if (!$mainPhone || !$this->idempotencyBlocker->tryLock($mainPhone)) {
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

        $response = $this->json(['status' => 'Прийнято'], 202);
        $response->headers->set('Connection', 'close');

        return $response;
    }

    #[Route('/api/users', name: 'get_all_users', methods: ['GET'])]
    public function records(Request $request, DocumentManager $dm): JsonResponse
    {
        $sortBy = $request->query->get('sortBy', 'phoneNumbers');
        $direction = $request->query->get('direction', 'desc');

        $allowedSortFields = ['firstName', 'lastName', 'phoneNumbers', 'ipAddress', 'country'];
        if (!in_array($sortBy, $allowedSortFields, true)) {
            return new JsonResponse(['error' => 'Недопустиме поле для сортування'], 400);
        }

        $users = $dm->getRepository(User::class)->findBy([], [$sortBy => $direction]);
        $jsonData = $this->serializer->serialize(['records' => $users], 'json');

        return new JsonResponse($jsonData, 200, [], true);
    }
}
