<?php declare(strict_types = 1);

namespace Neatous\Doctrine\Extensions\Translatable;

interface TranslationInterface
{
    public function setTranslatable(TranslatableInterface $translatable): void;

    public function getTranslatable(): TranslatableInterface;

    public function setLocale(string $locale): void;

    public function getLocale(): string;

    public function isEmpty(): bool;

    public static function getTranslatableEntityClass(): string;
}
