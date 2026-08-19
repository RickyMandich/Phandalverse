<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessGroup extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'color', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccessGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccessGroup::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Risale la catena di antenati (incluso il gruppo stesso).
     * Gerarchie tipicamente poco profonde: nessuna recursive CTE necessaria.
     */
    public function ancestorSlugs(): array
    {
        $slugs = [$this->slug];
        $current = $this;

        // guardia anti-loop nel caso qualcuno crei accidentalmente un ciclo parent_id
        $visited = [$this->id];

        while ($current->parent_id !== null) {
            $current = $current->parent;
            if ($current === null || in_array($current->id, $visited, true)) {
                break;
            }
            $visited[] = $current->id;
            $slugs[] = $current->slug;
        }

        return $slugs;
    }

    /**
     * Profondità del gruppo nella gerarchia (0 = radice).
     */
    public function depth(): int
    {
        return count($this->ancestorSlugs()) - 1;
    }
}