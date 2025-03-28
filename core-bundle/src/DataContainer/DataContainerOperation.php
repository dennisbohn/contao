<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\DataContainer;

use Contao\DataContainer;
use Contao\StringUtil;

/**
 * @implements \ArrayAccess<string, mixed>
 */
class DataContainerOperation implements \ArrayAccess
{
    private array $operation;
    private string|null $html = null;

    /**
     * @internal
     */
    public function __construct(private readonly string $name, array $operation, private readonly array $record, private readonly DataContainer $dataContainer)
    {
        $id = StringUtil::specialchars(rawurldecode((string) $record['id']));

        if (isset($operation['label'])) {
            // Copy and dereference pointer to $GLOBALS['TL_LANG']
            $label = $operation['label'];
            unset($operation['label']);

            if (\is_array($label)) {
                $operation['title'] = sprintf($label[1] ?? '', $id);
                $operation['label'] = $label[0] ?? $name;
            } else {
                $operation['label'] = $operation['title'] = sprintf($label, $id);
            }
        } else {
            $operation['label'] = $operation['title'] = $name;
        }

        $attributes = !empty($operation['attributes']) ? ' '.ltrim(sprintf($operation['attributes'], $id, $id)) : '';

        // Add the key as CSS class
        if (str_contains($attributes, 'class="')) {
            $attributes = str_replace('class="', 'class="'.$name.' ', $attributes);
        } else {
            $attributes = ' class="'.$name.'" '.$attributes;
        }

        $operation['attributes'] = $attributes;

        $this->operation = $operation;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->operation[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->operation[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->operation[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->operation[$offset]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    public function getDataContainer(): DataContainer
    {
        return $this->dataContainer;
    }

    public function getHtml(): string|null
    {
        return $this->html;
    }

    public function setHtml(string|null $html): self
    {
        $this->html = $html;

        return $this;
    }
}
