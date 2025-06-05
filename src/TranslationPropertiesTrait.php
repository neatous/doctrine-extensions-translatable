<?php declare(strict_types = 1);

namespace Neatous\Doctrine\Extensions\Translatable;

trait TranslationPropertiesTrait
{
    /** @var string */
    protected $locale;

    /**
     * Will be mapped to translatable entity by TranslatableSubscriber
     *
     * @var TranslatableInterface
     */
    protected $translatable;
}
