<?php
namespace App\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "users")]
class User
{
    #[MongoDB\Id]
    private ?string $id = null;

    #[MongoDB\Field(type: "string", name: "firstName")]
    private string $firstName;

    #[MongoDB\Field(type: "string", name: "lastName")]
    private string $lastName;

    #[MongoDB\Field(type: "collection", name: "phoneNumbers")]
    private array $phoneNumbers = [];

    #[MongoDB\Field(type: "string", name: "ipAddress")]
    private string $ipAddress;

    #[MongoDB\Field(type: "string", name: "country")]
    private string $country;

    // --- GETTERS & SETTERS ---

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPhoneNumbers(): array
    {
        return $this->phoneNumbers;
    }

    public function addPhone(string $phone): self
    {
        $this->phoneNumbers[] = $phone;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }
}
