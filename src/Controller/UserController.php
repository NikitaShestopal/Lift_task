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
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{

    public function __construct(
        private IdempotencyBlocker $idempotencyBlocker,
        private UserRequestValidator $requestValidator
    ) {}

    #[Route('/api/user', name: 'add_user', methods: ['POST'])]
    public function createUser(Request $request, MessageBusInterface $bus, ValidatorInterface $validator): JsonResponse
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

        $errors = $validator->validate($message);

        if (count($errors) > 0) {
            $resultErrors = [];
            foreach ($errors as $error) {
                $resultErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $resultErrors], 400);
        }

        $bus->dispatch($message);

        $response = $this->json(['status' => 'Accepted'], 202);
        $response->headers->set('Connection', 'close');

        return $response;
    }

    #[Route('/api/users', name: 'get_all_users', methods: ['GET'])]
    public function listUsers(Request $request, DocumentManager $dm): JsonResponse
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

        $responseRecords = [];

        foreach ($users as $user) {
            $responseRecords[] = [
                'id' => method_exists($user, 'getId') ? $user->getId() : null,
                'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
                'lastName' => method_exists($user, 'getLastName') ? $user->getLastName() : null,
                'ipAddress' => method_exists($user, 'getIpAddress') ? $user->getIpAddress() : null,
                'country' => method_exists($user, 'getCountry') ? $user->getCountry() : null,
                'phoneNumbers' => method_exists($user, 'getPhones') ? $user->getPhones() : (method_exists($user, 'getPhoneNumbers') ? $user->getPhoneNumbers() : []),
            ];
        }

        return new JsonResponse(['records' => $responseRecords], 200);
    }
}
