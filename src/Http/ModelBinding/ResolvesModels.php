<?php

declare(strict_types=1);

namespace Engelsystem\Http\ModelBinding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Engelsystem\Http\Request;
use Engelsystem\Models\Certification;

trait ResolvesModels
{
    /**
     * Resolve a model by UUID from request attributes.
     *
     * @template T of Model
     * @param class-string<T> $modelClass
     * @return T
     */
    protected function resolveModelByUuid(Request $request, string $modelClass, string $attributeName = 'uuid'): Model
    {
        $uuid = $request->getAttribute($attributeName);

        if (!$uuid) {
            throw new ModelNotFoundException('No UUID provided for model resolution');
        }

        $model = $modelClass::where('uuid', $uuid)->first();

        if (!$model) {
            throw new ModelNotFoundException('No ' . $modelClass . ' found with UUID: ' . $uuid);
        }

        return $model;
    }

    /**
     * Resolve a Certification model by UUID from request attributes.
     */
    protected function resolveCertification(Request $request, string $attributeName = 'uuid'): Certification
    {
        return $this->resolveModelByUuid($request, Certification::class, $attributeName);
    }
}
