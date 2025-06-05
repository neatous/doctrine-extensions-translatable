<?php declare(strict_types = 1);

namespace Neatous\Doctrine\Extensions\Translatable;

interface LocaleProviderInterface
{
    public function provideCurrentLocale(): ?string;

    public function provideFallbackLocale(): ?string;
}
