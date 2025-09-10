<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int                         $id
 * @property string                      $name
 * @property string                      $slug
 *
 * @property-read Collection|Privilege[] $privileges
 * @property-read Collection|User[]      $users
 *
 * @method static Builder|Group whereId($value)
 * @method static Builder|Group whereName($value)
 * @method static Builder|Group whereSlug($value)
 */
class Group extends BaseModel
{
    use HasFactory;

    /** @var string[] */
    protected $fillable = [ // phpcs:ignore
        'name',
        'slug',
    ];

    public function privileges(): BelongsToMany
    {
        return $this->belongsToMany(Privilege::class, 'group_privileges');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_groups');
    }

    /**
     * Generate a unique slug from a name
     *
     * @param string $name The name to generate a slug from
     * @param int|null $excludeId ID to exclude when checking for uniqueness (useful for updates)
     * @return string The generated unique slug
     */
    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Build the query to check for existing slugs
        $query = static::where('slug', $slug);

        // Exclude the current group if an ID is provided
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        // Check for conflicts and add counter if needed
        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            // Update the query with the new slug
            $query = static::where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
