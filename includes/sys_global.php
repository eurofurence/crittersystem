<?php

/**
 * Removes Zalgo text characters and other combining diacritical marks from a string.
 *
 * @param string $text The input text that may contain Zalgo characters or combining marks
 * @return string The cleaned text with Zalgo characters and combining marks removed
 *
 * @see https://dev.to/limonsafayet/check-if-a-string-contains-zalgo-text-10lg Reference implementation
 */
function cleanZalgoText(string $text): string
{
    // Refence code from: https://dev.to/limonsafayet/check-if-a-string-contains-zalgo-text-10lg
    // Normalize to NFC form if needed (requires intl extension)
    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::FORM_C);
    }

    // Remove combining diacritical marks (Zalgo characters are usually in Mn class)
    $text = preg_replace('/\p{Mn}+/u', '', $text);

    // Remove known problematic bytes like \xCC, \xCD (if they remain)
    $text = preg_replace('/[\xCC\xCD]/', '', $text);

    return $text;
}

/**
 * Cleans a given text by removing special characters, HTML tags, and unnecessary whitespace.
 * Optionally provides aggressive cleaning by normalizing spaces and removing control characters.
 *
 * @param string $text The input string to be cleaned.
 * @param bool $agressive Optional. Determines if aggressive cleaning should be applied. Default is false.
 * @return string The cleaned text.
 */
function globalCleanText(string $text, bool $agressive = false): string
{
    $text = cleanZalgoText($text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    if ($agressive) {
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove any remaining control characters (optional but helps avoid surprises)
         $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    }

    $text = trim($text);

    return $text;
}
