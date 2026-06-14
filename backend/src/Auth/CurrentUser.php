<?php

declare(strict_types=1);

namespace Piskari\Auth;

final class CurrentUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $name,
        public readonly ?string $picture,
        public readonly bool $isAdmin
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'picture' => $this->picture,
            'is_admin' => $this->isAdmin,
        ];
    }
}
