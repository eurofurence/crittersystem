<?php

namespace Engelsystem\Config;

use Illuminate\Support\Fluent;

class Config extends Fluent
{
    /**
     * The config values
     */
    protected $attributes = []; // phpcs:ignore

    /**
     * Retrieves a configuration value by key.
     *
     * If the provided key is null, returns all configuration attributes.
     * If the key exists, returns the corresponding value.
     * Otherwise, returns the specified default value.
     *
     * @param string|array|null $key     The configuration key or keys to retrieve, or null to retrieve all.
     * @param mixed             $default The default value to return if the key does not exist.
     * @return mixed                     The configuration value(s) or the default value.
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->attributes;
        }

        if ($this->has($key)) {
            return $this->attributes[$key];
        }

        return $default;
    }

    /**
     * Sets a configuration value or multiple values.
     *
     * If an array is provided as the first argument, each key-value pair in the array
     * will be set recursively. Otherwise, sets the specified key to the given value.
     *
     * @param string|array  $key    The configuration key to set, or an associative array of key-value pairs.
     * @param mixed|null    $value  The value to set for the given key. Ignored if $key is an array.
     *
     */
    public function set(mixed $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $configKey => $configValue) {
                $this->set($configKey, $configValue);
            }

            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Checks if the specified configuration key exists.
     *
     * @param mixed $key The configuration key to check for existence.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(mixed $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Removes the configuration value associated with the specified key.
     *
     * @param mixed $key The key of the configuration item to remove.
     * @return void
     */
    public function remove(mixed $key): void
    {
        $this->offsetUnset($key);
    }
}
