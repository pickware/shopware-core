<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\Struct\Uuid;

class JsonFieldAccessorBuilder implements FieldAccessorBuilderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function buildAccessor(string $root, Field $field, Context $context, string $accessor): ?FieldAccessor
    {
        /** @var StorageAware $field */
        if (!$field instanceof JsonField) {
            return null;
        }

        $accessor = preg_replace('#^' . $field->getPropertyName() . '#', '', $accessor);

        $parameter = 'json_path_' . Uuid::uuid4()->getHex();

        return new FieldAccessor(
            sprintf(
                'JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, :%s))',
                $root,
                $field->getStorageName(),
                $parameter
            ),
            [$parameter => '$' . $accessor]
        );
    }
}
