<?php
declare(strict_types=1);

namespace PPStudio\Http\Request;

final class ReservationSubmitRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $note,
        public readonly int $serviceId,
        public readonly string $day,
        public readonly string $time,
        public readonly string $source = 'web'
    ) {
    }

    public static function fromPost(array $post): self
    {
        return new self(
            trim((string) ($post['jmeno'] ?? '')),
            trim((string) ($post['email'] ?? '')),
            trim((string) ($post['telefon'] ?? '')),
            trim((string) ($post['poznamka'] ?? '')),
            (int) ($post['sluzba_id'] ?? 0),
            trim((string) ($post['rezervacni_datum'] ?? '')),
            trim((string) ($post['rezervacni_cas'] ?? ''))
        );
    }

    public function validationStatus(): ?string
    {
        if ($this->name === '' || $this->email === '' || $this->serviceId <= 0 || $this->day === '' || $this->time === '') {
            return 'missing';
        }

        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day) || ! preg_match('/^\d{2}:\d{2}$/', $this->time)) {
            return 'invalid_datetime';
        }

        return null;
    }

    public function dateTime(): string
    {
        return $this->day . ' ' . $this->time . ':00';
    }
}
