<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "First name can't be empty")]
        #[Assert\Length(min: 3, minMessage: "First name must be at least 3 characters long")]
        public string $firstName,

        #[Assert\NotBlank(message: "Last name can't be empty")]
        #[Assert\Length(min: 3, minMessage: "Last name must be at least 3 characters long")]
        public string $lastName,

        #[Assert\Count(min: 1, minMessage: "Phone numbers must be at least one number")]
        public array $phoneNumbers,

        public string $ipAddress
    ) {}
}
