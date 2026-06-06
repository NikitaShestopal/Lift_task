<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "Ім'я не повинно бути порожнім")]
        #[Assert\Length(min: 3, minMessage: "Ім'я повинно містити хоча б 3 символи")]
        public string $firstName,

        #[Assert\NotBlank(message: "Прізвище не повинно бути пустим")]
        #[Assert\Length(min: 3, minMessage: "Прізвище повинно містити хоча б 3 символи")]
        public string $lastName,

        #[Assert\Count(min: 1, minMessage: "В номерах телефоныв повинно бути вказано хоча б один номер телефона")]
        public array $phoneNumbers,

        public string $ipAddress
    ) {}
}
