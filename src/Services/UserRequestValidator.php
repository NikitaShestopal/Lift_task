<?php

namespace App\Services;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserRequestValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    public function validate(array $data): array
    {
        // Використовуємо іменовані аргументи замість конфігураційних масивів
        $constraints = new Assert\Collection(
            fields: [
                'firstName' => [
                    new Assert\NotBlank(message: "Ім'я обов'язкове"),
                    new Assert\Length(min: 2, minMessage: "Ім'я занадто коротке")
                ],
                'lastName' => [
                    new Assert\NotBlank(message: "Прізвище обов'язкове"),
                    new Assert\Length(min: 2, minMessage: "Прізвище занадто коротке")
                ],
                'phoneNumbers' => [
                    new Assert\NotBlank(message: "Список телефонів обов'язковий"),
                    new Assert\Type(type: 'array'),
                    new Assert\Count(min: 1, minMessage: "Вкажіть хоча б один телефон"),
                    new Assert\All(constraints: [
                        new Assert\NotBlank(message: "Номер телефону не може бути порожнім"),
                        new Assert\Regex(
                            pattern: '/^\+?[1-9]\d{1,14}$/',
                            message: "Некоректний формат телефону"
                        )
                    ])
                ],
            ],
            allowExtraFields: true,
            allowMissingFields: false
        );

        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) === 0) {
            return [];
        }

        $errors = [];
        foreach ($violations as $violation) {
            $propertyPath = str_replace(['[', ']'], ['/', ''], $violation->getPropertyPath());
            $propertyPath = trim($propertyPath, '/');

            $errors[$propertyPath][] = $violation->getMessage();
        }

        return $errors;
    }
}
