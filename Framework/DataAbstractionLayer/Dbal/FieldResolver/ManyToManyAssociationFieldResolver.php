<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class ManyToManyAssociationFieldResolver implements FieldResolverInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(DefinitionInstanceRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof ManyToManyAssociationField) {
            return false;
        }
        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();
        $table = $mapping::getEntityName();

        $mappingAlias = $root . '.' . $field->getPropertyName() . '.mapping';

        if ($query->hasState($mappingAlias)) {
            return true;
        }
        $query->addState($mappingAlias);

        $versionJoinCondition = '';
        /** @var string|EntityDefinition $definition */
        if ($definition::isVersionAware() && $field->is(CascadeDelete::class)) {
            $versionField = $definition::getEntityName() . '_version_id';
            $versionJoinCondition = ' AND #root#.version_id = #alias#.' . $versionField;
        }

        $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getLocalField());
        if ($field->is(Inherited::class) && $context->considerInheritance()) {
            $source = EntityDefinitionQueryHelper::escape($root) . '.' . EntityDefinitionQueryHelper::escape($field->getPropertyName());
        }

        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#source#' => $source,
            '#alias#' => EntityDefinitionQueryHelper::escape($mappingAlias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getMappingLocalColumn()),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($mappingAlias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#source# = #alias#.#reference_column#' . $versionJoinCondition
            )
        );

        $reference = $this->registry->get($field->getReferenceDefinition());
        $table = $reference::getEntityName();

        $alias = $root . '.' . $field->getPropertyName();

        $versionJoinCondition = '';
        if ($reference::isVersionAware()) {
            $versionField = '`' . $reference::getEntityName() . '_version_id`';
            $versionJoinCondition = ' AND #alias#.`version_id` = #mapping#.' . $versionField;
        }

        $referenceColumn = EntityDefinitionQueryHelper::escape($field->getReferenceField());
        if ($field->is(ReverseInherited::class) && $context->considerInheritance()) {
            /** @var ReverseInherited $flag */
            $flag = $field->getFlag(ReverseInherited::class);

            $referenceColumn = EntityDefinitionQueryHelper::escape($flag->getReversedPropertyName());
        }

        $ruleCondition = $queryHelper->buildRuleCondition($reference, $query, $alias, $context);
        if ($ruleCondition !== null) {
            $ruleCondition = ' AND ' . $ruleCondition;
        }

        $parameters = [
            '#mapping#' => EntityDefinitionQueryHelper::escape($mappingAlias),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getMappingReferenceColumn()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => $referenceColumn,
            '#root#' => EntityDefinitionQueryHelper::escape($root),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($mappingAlias),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#mapping#.#source_column# = #alias#.#reference_column# ' . $versionJoinCondition . $ruleCondition
            )
        );

        if ($definition->getClass() === $reference->getClass()) {
            return true;
        }

        if (!$reference::isInheritanceAware() || !$context->considerInheritance()) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference::getFields()->get('parent');

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);

        return true;
    }
}
