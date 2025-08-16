<?php

declare(strict_types=1);

namespace Engelsystem\Renderer\Twig\Extensions;

use Engelsystem\Http\Request;
use Engelsystem\Http\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension as TwigExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Url extends TwigExtension
{
    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        protected Request $request
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', [$this, 'getUrl']),
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('merge_query_params', [$this, 'mergeQueryParams']),
        ];
    }

    public function getUrl(string $path, array $parameters = []): string
    {
        // Fix legacy URLs
        $path = str_replace('_', '-', $path);

        return $this->urlGenerator->to($path, $parameters);
    }

    /**
     * Merge new query parameters with existing ones in a URL
     */
    public function mergeQueryParams(string $url, array $newParams = []): string
    {
        // Parse the URL
        $parsedUrl = parse_url($url);

        // Get existing query parameters
        $existingParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingParams);
        }

        // Get current request query parameters
        $currentParams = $this->request->query->all();

        // Merge: current params -> existing params -> new params
        $mergedParams = array_merge($currentParams, $existingParams, $newParams);

        // Remove empty parameters
        $mergedParams = array_filter($mergedParams, function ($value) {
            return $value !== null && $value !== '';
        });

        // Rebuild the URL
        $baseUrl = '';
        if (isset($parsedUrl['scheme'])) {
            $baseUrl .= $parsedUrl['scheme'] . '://';
        }
        if (isset($parsedUrl['host'])) {
            $baseUrl .= $parsedUrl['host'];
        }
        if (isset($parsedUrl['port'])) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }
        if (isset($parsedUrl['path'])) {
            $baseUrl .= $parsedUrl['path'];
        }

        // Add query string if we have parameters
        if (!empty($mergedParams)) {
            $baseUrl .= '?' . http_build_query($mergedParams);
        }

        // Add fragment if present
        if (isset($parsedUrl['fragment'])) {
            $baseUrl .= '#' . $parsedUrl['fragment'];
        }

        return $baseUrl;
    }
}
