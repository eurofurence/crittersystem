<?php

declare(strict_types=1);

namespace Engelsystem\Http\Validation;

use Carbon\Carbon;

/**
 * Validation helper for purge operations
 */
class PurgeRequestValidator
{
    /**
     * Get validation rules for purge requests
     */
    public static function rules(): array
    {
        return [
            // The internal validator does not support array/wildcard rules.
            // We only ensure presence here; content is sanitized later.
            'categories' => 'required',
            // Use custom DateTime rule with explicit format and the Before rule
            'cutoff_date' => 'required|dateTime:Y-m-d|before:today',
        ];
    }

    /**
     * Get custom validation messages
     */
    public static function messages(): array
    {
        return [
            'categories.required' => 'Please select at least one category to purge.',
            'cutoff_date.required' => 'Please select a cutoff date.',
            'cutoff_date.dateTime' => 'Please enter a valid date in YYYY-MM-DD format.',
            'cutoff_date.before' => 'Cutoff date must be in the past.',
        ];
    }

    /**
     * Validate and sanitize purge request data
     */
    public static function validateAndSanitize(array $data): array
    {
        // Sanitize categories
        if (isset($data['categories']) && is_array($data['categories'])) {
            $data['categories'] = array_filter($data['categories'], function ($category) {
                return in_array($category, ['users', 'shifts', 'news', 'logs']);
            });
        }

        // Sanitize date
        if (isset($data['cutoff_date'])) {
            try {
                $date = Carbon::parse($data['cutoff_date']);
                $data['cutoff_date'] = $date->format('Y-m-d');
            } catch (\Exception $e) {
                unset($data['cutoff_date']);
            }
        }

        return $data;
    }

    /**
     * Check if the request is valid for preview
     */
    public static function isValidForPreview(array $data): bool
    {
        return !empty($data['categories'])
            && is_array($data['categories'])
            && !empty($data['cutoff_date'])
            && Carbon::hasFormat($data['cutoff_date'], 'Y-m-d');
    }

    /**
     * Check if the request is valid for execution
     */
    public static function isValidForExecution(array $data): bool
    {
        return self::isValidForPreview($data)
            && isset($data['confirmation'])
            && $data['confirmation'] === 'DELETE';
    }

    /**
     * Get safe categories for processing
     */
    public static function getSafeCategories(array $data): array
    {
        $validCategories = ['users', 'shifts', 'news', 'logs'];

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            return [];
        }

        return array_intersect($data['categories'], $validCategories);
    }

    /**
     * Get safe cutoff date
     */
    public static function getSafeCutoffDate(array $data): ?Carbon
    {
        if (!isset($data['cutoff_date'])) {
            return null;
        }

        try {
            $date = Carbon::parse($data['cutoff_date']);

            // Ensure the date is in the past
            if ($date->isFuture()) {
                return null;
            }

            return $date;
        } catch (\Exception $e) {
            return null;
        }
    }
}
