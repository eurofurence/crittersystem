<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Carbon\Carbon;

/**
 * @property int         $id
 * @property string      $key
 * @property string      $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DigitalIdConfig extends BaseModel
{
    /** @var string[] */
    protected $fillable = [ // phpcs:ignore
        'key',
        'value',
    ];

    /** @var string[] */
    protected $casts = [ // phpcs:ignore
        'key' => 'string',
        'value' => 'string',
    ];

    /** @var string The table associated with the model */
    protected $table = 'digital_id_config'; // phpcs:ignore

    /**
     * Get a configuration value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();

        if (!$config) {
            return $default;
        }

        // Convert string values to appropriate types
        $value = $config->value;

        // Handle boolean values
        if (in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true';
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Set a configuration value
     */
    public static function setValue(string $key, mixed $value): bool
    {
        // Convert value to string for storage
        $stringValue = match (gettype($value)) {
            'boolean' => $value ? 'true' : 'false',
            'integer', 'double' => (string) $value,
            default => (string) $value,
        };

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue]
        )->wasRecentlyCreated || true;
    }

    /**
     * Get all configuration values as an array
     */
    public static function getAllConfig(): array
    {
        $configs = static::all();
        $result = [];

        foreach ($configs as $config) {
            $result[$config->key] = static::getValue($config->key);
        }

        return $result;
    }

    /**
     * Check if digital ID system is enabled
     */
    public static function isDigitalIdEnabled(): bool
    {
        return static::getValue('digital_id_enabled', true);
    }

    /**
     * Get the refresh interval in seconds
     */
    public static function getRefreshInterval(): int
    {
        return static::getValue('digital_id_refresh_interval', 120);
    }

    /**
     * Get the token overlap period in seconds
     */
    public static function getTokenOverlap(): int
    {
        return static::getValue('digital_id_token_overlap', 30);
    }

    /**
     * Set digital ID system enabled/disabled
     */
    public static function setDigitalIdEnabled(bool $enabled): bool
    {
        return static::setValue('digital_id_enabled', $enabled);
    }

    /**
     * Set refresh interval with validation
     */
    public static function setRefreshInterval(int $seconds): bool
    {
        // Minimum refresh interval is 30 seconds
        if ($seconds < 30) {
            throw new \InvalidArgumentException('Refresh interval must be at least 30 seconds');
        }

        return static::setValue('digital_id_refresh_interval', $seconds);
    }

    /**
     * Set token overlap period
     */
    public static function setTokenOverlap(int $seconds): bool
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Token overlap must be non-negative');
        }

        return static::setValue('digital_id_token_overlap', $seconds);
    }
}
