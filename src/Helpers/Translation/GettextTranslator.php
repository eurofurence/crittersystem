<?php

namespace Engelsystem\Helpers\Translation;

use Gettext\Translator;

class GettextTranslator extends Translator
{
    /**
     * @return string
     * @throws TranslationNotFound
     */
    protected function translate(?string $domain, ?string $context, string $original): string
    {
        $this->assertHasTranslation($domain, $context, $original);

        return parent::translate($domain, $context, $original);
    }

    /**
     * @return string
     * @throws TranslationNotFound
     */
    protected function translatePlural(
        ?string $domain,
        ?string $context,
        string $original,
        string $plural,
        int $value
    ): string {
        $this->assertHasTranslation($domain, $context, $original);

        return parent::translatePlural($domain, $context, $original, $plural, $value);
    }

    /**
     * @throws TranslationNotFound
     */
    protected function assertHasTranslation(?string $domain, ?string $context, string $original)
    {
        if ($this->getTranslation($domain, $context, $original)) {
            return;
        }

        throw new TranslationNotFound(implode('/', [$domain, $context, $original]));
    }
}
