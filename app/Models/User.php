<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;

#[Fillable([
    'sso', 'name', 'title',
    'first_name_th', 'last_name_th',
    'first_name_en', 'last_name_en',
    'gender', 'email', 'phone',
    'workline', 'department', 'position',
    'level', 'password', 'role_id', 'role_key',
    'supervisor', 'evaluator2', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ==================== เพิ่มตรงนี้ ====================

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function hasRole(string|array $roleKey): bool
    {
        if (is_array($roleKey)) {
            return in_array($this->role_key, $roleKey);
        }
        return $this->role_key === $roleKey;
    }

    public function isAdmin(): bool      { return $this->hasRole('admin'); }
    public function isHR(): bool         { return $this->hasRole('hr'); }
    public function isSupervisor(): bool { return $this->hasRole('supervisor'); }
    public function isDeptHead(): bool   { return $this->hasRole('dept_head'); }
    public function isDean(): bool       { return $this->hasRole('dean'); }

    // ======================================================
}