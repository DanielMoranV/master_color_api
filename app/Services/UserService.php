<?php

namespace App\Services;

use App\Models\User;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\HasPasswordHashing;

class UserService
{
    use HasPasswordHashing;
    public function getAllUsers(int $perPage = 15): LengthAwarePaginator
    {
        return Cache::remember('users_paginated_' . $perPage . '_' . request('page', 1), 300, function () use ($perPage) {
            return User::with('role')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    public function getUserById(int $id): ?User
    {
        return Cache::remember("user_{$id}", 600, function () use ($id) {
            return User::with('role')->find($id);
        });
    }

    public function createUser(UserStoreRequest $request): User
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            $this->preparePasswordForStorage($validated);

            $user = User::create($validated);

            $this->clearUserCache();
            
            DB::commit();
            
            return $user->load('role');
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateUser(UserUpdateRequest $request, int $id): ?User
    {
        $user = User::find($id);
        
        if (!$user) {
            return null;
        }

        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            $this->preparePasswordForStorage($validated);

            $user->update($validated);
            
            $this->clearUserCache($id);
            
            DB::commit();
            
            return $user->refresh()->load('role');
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteUser(int $id): bool
    {
        $user = User::find($id);
        
        if (!$user) {
            return false;
        }

        DB::beginTransaction();
        
        try {
            $user->delete();
            
            $this->clearUserCache($id);
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function clearUserCache(?int $userId = null): void
    {
        if ($userId) {
            Cache::forget("user_{$userId}");
        }
        
        Cache::flush();
    }
}