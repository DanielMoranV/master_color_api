<?php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;

trait HasPasswordHashing
{
    protected function hashPassword(string $password): string
    {
        return Hash::make($password);
    }

    protected function verifyPassword(string $password, string $hashedPassword): bool
    {
        return Hash::check($password, $hashedPassword);
    }

    protected function preparePasswordForStorage(array &$data): void
    {
        if (isset($data['password'])) {
            $data['password'] = $this->hashPassword($data['password']);
        }
    }
}