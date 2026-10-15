<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Enums\Sexe;
use App\Enums\SituationFamiliale;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['matricule', 'nom', 'prenom', 'cin', 'date_naissance', 'numero_cnasp', 'adresse', 'sexe', 'situation_familiale', 'department_id', 'position', 'hire_date', 'contract_type', 'phone_pro', 'email_pro'])]
#[Hidden(['photo_path'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'actif',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'date_naissance' => 'date',
            'status' => EmployeeStatus::class,
            'contract_type' => ContractType::class,
            'sexe' => Sexe::class,
            'situation_familiale' => SituationFamiliale::class,
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Recherche par nom, prénoms ou matricule.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->where('nom', 'like', "%{$term}%")
                ->orWhere('prenom', 'like', "%{$term}%")
                ->orWhere('matricule', 'like', "{$term}%");
        });
    }
}
