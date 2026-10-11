<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'employee_id', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Suppléances en cours, chargées une fois par requête.
     *
     * @var Collection<int, Delegation>|null
     */
    private ?Collection $currentDelegations = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(RoleName $role): bool
    {
        return $this->roles->contains('name', $role->value);
    }

    /**
     * Permission détenue par un rôle du compte, ou reçue par une suppléance en cours.
     */
    public function hasPermission(PermissionName $permission): bool
    {
        return $this->hasDirectPermission($permission) || $this->delegatorFor($permission) !== null;
    }

    public function hasDirectPermission(PermissionName $permission): bool
    {
        return $this->permissionNames()->contains($permission->value);
    }

    /**
     * Personne remplacée dont la suppléance donne cette permission, si le compte ne la détient pas lui-même.
     */
    public function delegatorFor(PermissionName $permission): ?int
    {
        if ($this->hasDirectPermission($permission)) {
            return null;
        }

        $this->currentDelegations ??= $this->receivedDelegations()->current()->get();

        return $this->currentDelegations
            ->first(fn (Delegation $delegation): bool => in_array($permission, $delegation->role->delegablePermissions(), true))
            ?->delegator_id;
    }

    /**
     * @return HasMany<Delegation, $this>
     */
    public function receivedDelegations(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegate_id');
    }

    /**
     * @return HasMany<Delegation, $this>
     */
    public function givenDelegations(): HasMany
    {
        return $this->hasMany(Delegation::class, 'delegator_id');
    }

    /**
     * Permissions des rôles et des suppléances en cours.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames(): Collection
    {
        $delegated = collect(PermissionName::cases())
            ->filter(fn (PermissionName $permission): bool => $this->delegatorFor($permission) !== null)
            ->map(fn (PermissionName $permission): string => $permission->value);

        return $this->permissionNames()->merge($delegated)->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->flatMap->permissions->pluck('name')->unique()->values();
    }
}
