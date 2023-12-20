<?php

declare(strict_types=1);

namespace MoonShine\Traits\Resource;

use MoonShine\Enums\PageType;
use MoonShine\Exceptions\FilterException;
use MoonShine\Fields\Fields;
use MoonShine\Support\Filters;
use Throwable;

trait ResourceWithFields
{
    public function fields(): array
    {
        return [];
    }

    public function getFields(): Fields
    {
        return Fields::make($this->fields());
    }

    public function indexFields(): array
    {
        return [];
    }

    /**
     * @throws Throwable
     */
    public function getIndexFields(): Fields
    {
        $fields = $this->getPages()
            ->findByType(PageType::INDEX)
            ?->fields();

        if (empty($fields)) {
            $fields = $this->indexFields()
                ?: $this->fields();
        }

        return Fields::make($fields)
            ->onlyFields(withWrappers: true)
            ->indexFields();
    }

    public function formFields(): array
    {
        return [];
    }

    /**
     * @throws Throwable
     */
    public function getFormFields(bool $withOutside = false): Fields
    {
        $fields = $this->getPages()
            ->findByType(PageType::FORM)
            ?->fields();

        if (empty($fields)) {
            $fields = $this->formFields()
                ?: $this->fields();
        }

        return Fields::make($fields)
            ->formFields(withOutside: $withOutside);
    }

    public function detailFields(): array
    {
        return [];
    }

    /**
     * @throws Throwable
     */
    public function getDetailFields(bool $withOutside = false, bool $onlyOutside = false): Fields
    {
        $fields = $this->getPages()
            ->findByType(PageType::DETAIL)
            ?->fields();

        if (empty($fields)) {
            $fields = $this->detailFields()
                ?: $this->fields();
        }

        return Fields::make($fields)
            ->onlyFields(withWrappers: true)
            ->detailFields(withOutside: $withOutside, onlyOutside: $onlyOutside);
    }

    /**
     * @throws Throwable
     */
    public function getOutsideFields(): Fields
    {
        $fields = $this->getPages()
            ->findByType(PageType::FORM)
            ?->fields();

        if (empty($fields)) {
            $fields = $this->formFields()
                ?: $this->fields();
        }

        return Fields::make($fields)
            ->onlyFields()
            ->onlyOutside();
    }

    public function filters(): array
    {
        return [];
    }

    /**
     * @throws Throwable
     */
    public function getFilters(): Fields
    {
        $filters = Fields::make($this->filters())
            ->withoutOutside()
            ->wrapNames('filters');

        $filters->each(function ($filter): void {
            if (in_array($filter::class, Filters::NO_FILTERS)) {
                throw new FilterException("You can't use " . $filter::class . " inside filters.");
            }
        });

        return $filters;
    }
}
