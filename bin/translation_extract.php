#!/usr/bin/env php
<?php

/*
 * Make it executable or run with PHP
 * chmod +x extract-translations.php
 *
 * No arguments - will use the current directory and export to translations.pot
 * php extract-translations.php
 *
 * With custom directory and output file
 * php extract-translations.php /path/to/search output.pot
 */


class TranslationStringExtractor
{
    private array $translations = [];

    public function extractFromDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = $file->getExtension();
            if (!in_array($extension, ['php', 'twig'])) {
                continue;
            }

            $this->processFile($file->getRealPath(), $extension);
        }
    }

    private function processFile(string $filePath, string $extension): void
    {
        $content = file_get_contents($filePath);
        $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $filePath);

        if ($extension === 'php') {
            $this->extractFromPHP($content, $relativePath);
        } else {
            $this->extractFromTwig($content, $relativePath);
        }
    }

    private function extractFromPHP(string $content, string $filePath): void
    {
        // Match __('string') or __("string")
        preg_match_all('/__\(\s*([\'"])(.*?)\1\s*[,\)]/', $content, $matches);

        if (!empty($matches[2])) {
            foreach ($matches[2] as $match) {
                $this->addTranslation($match, $filePath);
            }
        }
    }

    private function extractFromTwig(string $content, string $filePath): void
    {
        // Match {{ '__'('string') }} or {% trans %}string{% endtrans %}
        // preg_match_all('/\{\{\s*\'__\'\s*\(\s*([\'"])(.*?)\1\s*\)\s*\}\}|\{\%\s*trans\s*\%\}(.*?)\{\%\s*endtrans\s*\%\}/', $content, $matches);

        // Match '__'('string') or __('string') or {{ '__'('string') }} or {% trans %}string{% endtrans %}
        preg_match_all('/__\s*\(\s*([\'"])(.*?)\1\s*(?:,|\))/', $content, $matches);

        if (!empty($matches[2])) {
            foreach ($matches[2] as $match) {
                if (!empty($match)) {
                    // $this->addTranslation($match, $filePath);
                    $need_cut = strpos($match, "', [");
                    if ($need_cut) {
                        $this->addTranslation(substr($match, 0, $need_cut), $filePath);
                    } else {
                        $this->addTranslation($match, $filePath);
                    }
                }
            }
        }

        if (!empty($matches[3])) {
            foreach ($matches[3] as $match) {
                if (!empty($match)) {
                    // $this->addTranslation(trim($match), $filePath);
                    $need_cut = strpos($match, "', [");
                    if ($need_cut) {
                        $this->addTranslation(substr($match, 0, $need_cut), $filePath);
                    } else {
                        $this->addTranslation($match, $filePath);
                    }
                }
            }
        }
    }

    private function addTranslation(string $string, string $filePath): void
    {
        if (!isset($this->translations[$string])) {
            $this->translations[$string] = [];
        }
        $this->translations[$string][] = $filePath;
    }

    public function generatePOTemplate(string $outputFile): void
    {
        $output = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\n";

        foreach ($this->translations as $string => $files) {
            $output .= "#: " . implode("\n#: ", array_unique($files)) . "\n";
            $output .= "msgid \"" . addcslashes($string, '"\\') . "\"\n";
            $output .= "msgstr \"\"\n\n";
        }

        file_put_contents($outputFile, $output);
        echo sprintf(
            "Found %d unique strings in %d files. Output saved to %s\n",
            count($this->translations),
            count(array_unique(array_merge(...array_values($this->translations)))),
            $outputFile
        );
    }
}

// Check if script is run from command line
if (PHP_SAPI !== 'cli') {
    die('This script can only be run from the command line.');
}

// Get directory from command line argument or use current directory
$searchDirectory = $argv[1] ?? getcwd();
$outputFile = $argv[2] ?? 'translations.pot';

echo "\n" . str_repeat('-', 80) . "\n";
echo "         Translation Extractor script\n";
echo str_repeat('-', 80) . "\n\n";
echo sprintf("Extracting translations from %s\n", $searchDirectory);
echo "Please wait...\n";

$extractor = new TranslationStringExtractor();
$extractor->extractFromDirectory($searchDirectory);
$extractor->generatePOTemplate($outputFile);
