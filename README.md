# Doctrine Extensions: Translatable

A Doctrine extension for managing translatable entities and their translations. This library was extracted from the `KnpLabs/DoctrineBehaviors` project to provide a more lightweight solution for translatable behavior.

## Installation

This library is available via Composer.

First, make sure you have Composer installed. If not, you can follow the instructions [here](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos).

Then, require the package in your project:

```bash
composer require neatous/doctrine-extensions-translatable
```

This command will add `neatous/doctrine-extensions-translatable` to your `composer.json` file and install the necessary dependencies, including `doctrine/orm`, `doctrine/persistence`, `doctrine/collections`, and `nette/utils`.

## Usage

### 1. Define your Translatable Entity

Your translatable entity should use the `TranslatableTrait` and implement `TranslatableInterface`.

```php
<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Neatous\Doctrine\Extensions\Translatable\TranslatableInterface;
use Neatous\Doctrine\Extensions\Translatable\TranslatableTrait;

#[ORM\Entity]
class YourEntity implements TranslatableInterface
{
    use TranslatableTrait; // Provides translation methods and properties

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // ... other properties specific to YourEntity ...

    public function __construct()
    {
        $this->initializeTranslationsCollection(); // Important: call this in your constructor
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // You can define magic methods for direct access to translated fields if needed
    // For example, __call for missing properties, or __get for direct access.
    // However, it's generally recommended to access translations explicitly via translate() or getTranslations().
}
```

### 2. Define your Translation Entity

Each translatable entity needs an associated translation entity. This entity will hold the translatable fields for a specific locale. It should use `TranslationTrait` and implement `TranslationInterface`.

```php
<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Neatous\Doctrine\Extensions\Translatable\TranslationInterface;
use Neatous\Doctrine\Extensions\Translatable\TranslationTrait;

#[ORM\Entity]
class YourEntityTranslation implements TranslationInterface
{
    use TranslationTrait; // Provides locale, translatable association, and isEmpty method

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}
```

### 3. Configure Doctrine Mapping

The `EventSubscriber` will automatically map the one-to-many relationship between your translatable entity and its translations. However, you need to ensure Doctrine can find your entities.

Make sure your `YourEntity` class includes a constructor that calls `initializeTranslationsCollection()`:

```php
// In YourEntity.php
public function __construct()
{
    $this->initializeTranslationsCollection(); // Crucial for initializing the translations collection
}
```

And your `YourEntityTranslation` class should specify its translatable entity class:

```php
// In YourEntityTranslation.php
// The getTranslatableEntityClass method is automatically provided by TranslationTrait
// and infers the translatable entity name by removing "Translation" suffix.
// So for YourEntityTranslation, it will look for YourEntity.
// If your naming convention differs, you might need to override this method.
public static function getTranslatableEntityClass(): string
{
    return YourEntity::class; // Explicitly define if auto-inference is not suitable
}
```

### 4. Register the Event Subscriber

You need to register the `EventSubscriber` in your Doctrine configuration. This is typically done in your dependency injection container.

You will also need to provide an implementation of `LocaleProviderInterface`.

#### Example (Symfony `services.yaml`):

```yaml
# config/services.yaml
services:
    # ... your existing services ...

    Neatous\Doctrine\Extensions\Translatable\LocaleProviderInterface:
        class: App\Service\MyLocaleProvider # Replace with your actual locale provider class
        # You might need to inject request stack or other locale sources here
        # arguments: ['@request_stack']

    neatous.doctrine_extensions.translatable.event_subscriber:
        class: Neatous\Doctrine\Extensions\Translatable\EventSubscriber
        arguments:
            - '@Neatous\Doctrine\Extensions\Translatable\LocaleProviderInterface'
            - 'EAGER' # or 'LAZY', 'EXTRA_LAZY' for translatableFetchMode
            - 'EAGER' # or 'LAZY', 'EXTRA_LAZY' for translationFetchMode
        tags:
            - { name: doctrine.event_subscriber }
```

#### Example `MyLocaleProvider.php`:

```php
<?php declare(strict_types=1);

namespace App\Service;

use Neatous\Doctrine\Extensions\Translatable\LocaleProviderInterface;
// use Symfony\Component\HttpFoundation\RequestStack; // Example if using Symfony

class MyLocaleProvider implements LocaleProviderInterface
{
    // private RequestStack $requestStack; // Example if using Symfony

    // public function __construct(RequestStack $requestStack) // Example if using Symfony
    // {
    //     $this->requestStack = $requestStack;
    // }

    public function provideCurrentLocale(): ?string
    {
        // Example: Get locale from Symfony Request
        // return $this->requestStack->getCurrentRequest()?->getLocale();

        // Example: Return a hardcoded locale or from session/config
        return 'en';
    }

    public function provideFallbackLocale(): ?string
    {
        // Example: Return a hardcoded fallback locale
        return 'en';
    }
}
```

### 5. Using Translatable Entities

Now you can interact with your translatable entity:

```php
<?php declare(strict_types=1);

use App\Entity\YourEntity;
use App\Entity\YourEntityTranslation;
use Doctrine\ORM\EntityManagerInterface;

final class ExampleUsage
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function run(): void
    {
        // Create a new translatable entity
        $entity = new YourEntity();
        $entity->setDefaultLocale('en'); // Set a default locale

        // Translate to current locale (e.g., 'en')
        $translationEn = $entity->translate('en');
        $translationEn->setTitle('Hello World');
        $translationEn->setContent('This is the content in English.');

        // Translate to another locale (e.g., 'fr')
        $translationFr = $entity->translate('fr');
        $translationFr->setTitle('Bonjour le monde');
        $translationFr->setContent('Ceci est le contenu en français.');

        // Translate to a third locale (e.g., 'de') - this will be added to newTranslations
        $translationDe = $entity->translate('de');
        $translationDe->setTitle('Hallo Welt');

        $this->entityManager->persist($entity);

        // Before flushing, merge newly created translations to persist them
        $entity->mergeNewTranslations();

        $this->entityManager->flush();

        echo "--- After Persisting ---\n";

        // Retrieve and display translations
        $retrievedEntity = $this->entityManager->getRepository(YourEntity::class)->find($entity->getId());

        if ($retrievedEntity) {
            echo "Current Locale: " . $retrievedEntity->getCurrentLocale() . "\n"; // Will be set by subscriber
            echo "Default Locale: " . $retrievedEntity->getDefaultLocale() . "\n";

            // Access translation for a specific locale
            $enTranslation = $retrievedEntity->translate('en', false); // false to not create new if not exists
            echo "English Title: " . $enTranslation->getTitle() . "\n";
            echo "English Content: " . $enTranslation->getContent() . "\n";

            $frTranslation = $retrievedEntity->translate('fr', false);
            echo "French Title: " . $frTranslation->getTitle() . "\n";
            echo "French Content: " . $frTranslation->getContent() . "\n";

            // Access translation for an unsupported locale, which might fallback
            $deTranslation = $retrievedEntity->translate('de', true); // true to allow fallback
            echo "German Title (possibly fallback): " . $deTranslation->getTitle() . "\n";

            // Iterating through all translations
            echo "--- All Translations ---\n";
            foreach ($retrievedEntity->getTranslations() as $locale => $translation) {
                echo "Locale: {$locale}, Title: {$translation->getTitle()}, Content: {$translation->getContent()}\n";
            }
        }
    }
}
```

## Contributing

Contributions are welcome! Please feel free to open issues or pull requests on the GitHub repository.

## License

This library is open-sourced under the MIT License.
```