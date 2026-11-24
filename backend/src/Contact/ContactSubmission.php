<?php

declare(strict_types=1);

namespace ContactForm\Contact;

final readonly class ContactSubmission
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $address,
        public string $profession,
        public string $fieldOne,
        public string $fieldTwo,
        public string $fieldThree,
        public string $message,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'profession' => $this->profession,
            'field_one' => $this->fieldOne,
            'field_two' => $this->fieldTwo,
            'field_three' => $this->fieldThree,
            'message' => $this->message,
        ];
    }

    public static function fromArray(array $values): self
    {
        $read = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';

        return new self(
            $read('name'),
            $read('email'),
            $read('phone'),
            $read('address'),
            $read('profession'),
            $read('field_one'),
            $read('field_two'),
            $read('field_three'),
            $read('message'),
        );
    }
}
