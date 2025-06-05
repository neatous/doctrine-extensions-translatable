<?php declare(strict_types = 1);

namespace Neatous\Doctrine\Extensions\Translatable;

use Doctrine\Common\EventSubscriber as DoctrineEventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use ReflectionClass;
use function assert;

final class EventSubscriber implements DoctrineEventSubscriber
{
    public const string LOCALE = 'locale';

    private LocaleProviderInterface $localeProvider;
    private int $translatableFetchMode;

    private int $translationFetchMode;

    public function __construct(
        LocaleProviderInterface $localeProvider,
        string $translatableFetchMode,
        string $translationFetchMode,
    )
    {
        $this->localeProvider = $localeProvider;
        $this->translatableFetchMode = $this->convertFetchString($translatableFetchMode);
        $this->translationFetchMode = $this->convertFetchString($translationFetchMode);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();

        $classMetadataReflClass = $classMetadata->reflClass;

        if (!$classMetadataReflClass instanceof ReflectionClass) {
            // Class has not yet been fully built, ignore this event
            return;
        }

        if ($classMetadata->isMappedSuperclass) {
            return;
        }

        if (is_a($classMetadataReflClass->getName(), TranslatableInterface::class, true)) {
            $this->mapTranslatable($classMetadata);

            return;
        }

        if (is_a($classMetadataReflClass->getName(), TranslationInterface::class, true)) {
            $this->mapTranslation($classMetadata, $loadClassMetadataEventArgs->getObjectManager());
        }
    }

    public function postLoad(PostLoadEventArgs $lifecycleEventArgs): void
    {
        $this->setLocales($lifecycleEventArgs);
    }

    public function prePersist(PrePersistEventArgs $lifecycleEventArgs): void
    {
        $this->setLocales($lifecycleEventArgs);
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
            Events::postLoad,
            Events::prePersist,
        ];
    }

    /**
     * Convert string FETCH mode to required string
     */
    private function convertFetchString(string|int $fetchMode): int
    {
        if (is_int($fetchMode)) {
            return $fetchMode;
        }

        if ($fetchMode === 'EAGER') {
            return ClassMetadata::FETCH_EAGER;
        }

        if ($fetchMode === 'EXTRA_LAZY') {
            return ClassMetadata::FETCH_EXTRA_LAZY;
        }

        return ClassMetadata::FETCH_LAZY;
    }

    /** @phpstan-ignore missingType.generics */
    private function mapTranslatable(ClassMetadata $classMetadataInfo): void
    {
        if ($classMetadataInfo->hasAssociation('translations')) {
            return;
        }

        $classMetadataInfo->mapOneToMany([
            'fieldName' => 'translations',
            'mappedBy' => 'translatable',
            'indexBy' => self::LOCALE,
            'cascade' => ['persist', 'remove'],
            'fetch' => $this->translatableFetchMode,
            'targetEntity' => $classMetadataInfo->getReflectionClass()
                ->getMethod('getTranslationEntityClass')
                ->invoke(null),
            'orphanRemoval' => true,
        ]);
    }

    /** @phpstan-ignore missingType.generics */
    private function mapTranslation(ClassMetadata $classMetadataInfo, ObjectManager $objectManager): void
    {
        if (!$classMetadataInfo->hasAssociation('translatable')) {
            $targetEntity = $classMetadataInfo->getReflectionClass()
                ->getMethod('getTranslatableEntityClass')
                ->invoke(null);

            /** @phpstan-ignore argument.templateType */
            $classMetadata = $objectManager->getClassMetadata($targetEntity);
            assert($classMetadata instanceof ClassMetadata);

            $singleIdentifierFieldName = $classMetadata->getSingleIdentifierFieldName();

            $classMetadataInfo->mapManyToOne([
                'fieldName' => 'translatable',
                'inversedBy' => 'translations',
                'cascade' => ['persist'],
                'fetch' => $this->translationFetchMode,
                'joinColumns' => [[
                    'name' => 'translatable_id',
                    'referencedColumnName' => $singleIdentifierFieldName,
                    'onDelete' => 'CASCADE',
                ]],
                'targetEntity' => $targetEntity,
            ]);
        }

        $name = $classMetadataInfo->getTableName() . '_unique_translation';

        if (!$this->hasUniqueTranslationConstraint($classMetadataInfo, $name) &&
            $classMetadataInfo->getName() === $classMetadataInfo->rootEntityName) {
            $classMetadataInfo->table['uniqueConstraints'][$name] = [
                'columns' => ['translatable_id', self::LOCALE],
            ];
        }

        if (!$classMetadataInfo->hasField(self::LOCALE) && !$classMetadataInfo->hasAssociation(self::LOCALE)) {
            $classMetadataInfo->mapField([
                'fieldName' => self::LOCALE,
                'type' => 'string',
                'length' => 5,
            ]);
        }
    }

    /** @phpstan-ignore missingType.generics */
    private function setLocales(LifecycleEventArgs $lifecycleEventArgs): void
    {
        $entity = $lifecycleEventArgs->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $currentLocale = $this->localeProvider->provideCurrentLocale();

        if ($currentLocale) {
            $entity->setCurrentLocale($currentLocale);
        }

        $fallbackLocale = $this->localeProvider->provideFallbackLocale();

        if ($fallbackLocale) {
            $entity->setDefaultLocale($fallbackLocale);
        }
    }

    /** @phpstan-ignore missingType.generics */
    private function hasUniqueTranslationConstraint(ClassMetadata $classMetadataInfo, string $name): bool
    {
        return isset($classMetadataInfo->table['uniqueConstraints'][$name]);
    }
}
