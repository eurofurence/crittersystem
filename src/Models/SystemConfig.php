<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Carbon\Carbon;
use Engelsystem\Models\User\User;
use Engelsystem\Models\User\UsesUserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents in-database system configuration
 *
 * @property int         $id
 * @property Carbon|null $created_at
 * @property int|null    $created_by
 * @property string      $json
 *
 * @property-read User|null $creator
 */
class SystemConfig extends BaseModel
{
    use HasFactory;
    use UsesUserModel;

    /** @var bool enable timestamps for created_at */
    public $timestamps = true; // phpcs:ignore

    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'created_by' => 'integer',
    ];

    /** @var array<string> */
    protected $fillable = [ // phpcs:ignore
        'created_at',
        'created_by',
        'json',
    ];

    /**
     * Return creating user
     *
     * @return User
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return PHP array parsed from stored JSON
     *
     * @return array
     */
    public function getJson(): array
    {
        // TODO: Error handling?
        $parsed_json = json_decode($this->json, true);
        return $parsed_json;
    }

}
