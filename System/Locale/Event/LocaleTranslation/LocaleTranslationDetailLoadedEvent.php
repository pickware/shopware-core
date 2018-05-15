<?php declare(strict_types=1);

namespace Shopware\System\Locale\Event\LocaleTranslation;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\System\Locale\Collection\LocaleTranslationDetailCollection;
use Shopware\System\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class LocaleTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LocaleTranslationDetailCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationDetailCollection $localeTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getLocaleTranslations(): LocaleTranslationDetailCollection
    {
        return $this->localeTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->localeTranslations->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->localeTranslations->getLocales(), $this->context);
        }
        if ($this->localeTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->localeTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
