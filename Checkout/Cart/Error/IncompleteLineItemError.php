<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

class IncompleteLineItemError extends Error
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $property;

    public function __construct(string $id, string $property)
    {
        $this->key = $id;
        $this->property = $property;
        $this->message = sprintf(
            'Line item "%s" incomplete. Property "%s" missing.',
            $id,
            $property
        );

        parent::__construct($this->message);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMessageKey(): string
    {
        return $this->property;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }
}
