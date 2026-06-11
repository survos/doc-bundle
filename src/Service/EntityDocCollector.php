<?php

namespace Survos\DocBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Survos\FieldBundle\Registry\EntityMetaRegistry;

/**
 * Gathers entity docs into plain arrays the doc commands render, plus a Mermaid ER
 * diagram of the schema.
 *
 * When field-bundle's EntityMetaRegistry is available (it almost always is) it
 * drives the curated, described entity list; Doctrine supplies field/relation
 * structure. Without it, we fall back to Doctrine's full metadata enriched by
 * reflecting #[EntityMeta] directly. doctrine/orm is always present here (a
 * transitive requirement of jawira/doctrine-diagram-bundle).
 */
final class EntityDocCollector
{
    private const ENTITY_META_ATTRIBUTE = 'Survos\\FieldBundle\\Attribute\\EntityMeta';

    public function __construct(
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?EntityMetaRegistry $registry = null,
    ) {
    }

    /**
     * @return list<array{
     *   class: string, short: string, table: string,
     *   label: ?string, description: ?string, group: ?string, code: ?string,
     *   api: bool, meili: bool,
     *   ids: list<string>,
     *   fields: list<array{name: string, type: string, nullable: bool, pk: bool}>,
     *   relations: list<array{name: string, target: string, toMany: bool}>
     * }>
     */
    public function collect(): array
    {
        if ($this->em === null) {
            return []; // no EntityManager service (app without Doctrine)
        }

        $entities = [];
        // Drive the list from Doctrine (the entities actually mapped in this app);
        // the registry only enriches each one with description/group/etc.
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $cm) {
            if ($cm->isMappedSuperclass || $cm->isEmbeddedClass) {
                continue;
            }
            $class = $cm->getName();

            $ids = $cm->getIdentifierFieldNames();

            $fields = [];
            foreach ($cm->getFieldNames() as $field) {
                $fields[] = [
                    'name' => $field,
                    'type' => (string) $cm->getTypeOfField($field),
                    'nullable' => $cm->isNullable($field),
                    'pk' => in_array($field, $ids, true),
                ];
            }

            $relations = [];
            foreach (array_keys($cm->getAssociationMappings()) as $name) {
                $relations[] = [
                    'name' => (string) $name,
                    'target' => $this->shortName($cm->getAssociationTargetClass((string) $name)),
                    'toMany' => $cm->isCollectionValuedAssociation((string) $name),
                ];
            }

            $meta = $this->meta($class);
            $entities[] = [
                'class' => $class,
                'short' => $this->shortName($class),
                'table' => $cm->getTableName(),
                'label' => $meta['label'],
                'description' => $meta['description'],
                'group' => $meta['group'],
                'code' => $meta['code'],
                'api' => $meta['api'],
                'meili' => $meta['meili'],
                'ids' => $ids,
                'fields' => $fields,
                'relations' => $relations,
            ];
        }

        usort($entities, static fn (array $a, array $b): int => strcmp($a['short'], $b['short']));

        return $entities;
    }

    /**
     * A Mermaid erDiagram of the schema: an attribute block per entity plus one
     * relationship line per association (deduped across owning/inverse sides).
     *
     * @param list<array<string, mixed>> $entities
     */
    public function mermaid(array $entities): string
    {
        $known = array_column($entities, null, 'short');

        $lines = ['erDiagram'];
        foreach ($entities as $entity) {
            $lines[] = sprintf('  %s {', $entity['short']);
            foreach ($entity['fields'] as $field) {
                $lines[] = sprintf(
                    '    %s %s%s',
                    $this->mermaidType($field['type']),
                    $field['name'],
                    $field['pk'] ? ' PK' : ''
                );
            }
            $lines[] = '  }';
        }

        $seen = [];
        foreach ($entities as $entity) {
            foreach ($entity['relations'] as $relation) {
                if (!isset($known[$relation['target']])) {
                    continue; // target isn't a documented entity (e.g. a third-party class)
                }
                $key = $this->relationKey($entity['short'], $relation['target'], $relation['name']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $lines[] = sprintf(
                    '  %s %s %s : "%s"',
                    $entity['short'],
                    $relation['toMany'] ? '||--o{' : '}o--||',
                    $relation['target'],
                    $relation['name']
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Human metadata for an entity: from the field-bundle registry when present,
     * otherwise from a reflected #[EntityMeta] attribute.
     *
     * @return array{label: ?string, description: ?string, group: ?string, code: ?string, api: bool, meili: bool}
     */
    private function meta(string $class): array
    {
        $descriptor = $this->registry?->get($class);
        if ($descriptor !== null) {
            return [
                'label' => $descriptor->label ?: null,
                'description' => $descriptor->description,
                'group' => $descriptor->group ?: null,
                'code' => $descriptor->code ?: null,
                'api' => $descriptor->hasApiResource,
                'meili' => $descriptor->hasMeiliIndex,
            ];
        }

        $attr = $this->reflectedEntityMeta($class);

        return [
            'label' => $attr['label'] ?? null,
            'description' => $attr['description'] ?? null,
            'group' => $attr['group'] ?? null,
            'code' => null,
            'api' => false,
            'meili' => false,
        ];
    }

    /**
     * @return array{label?: ?string, description?: ?string, group?: ?string}
     */
    private function reflectedEntityMeta(string $class): array
    {
        if (!class_exists(self::ENTITY_META_ATTRIBUTE)) {
            return [];
        }
        $attributes = (new \ReflectionClass($class))->getAttributes(self::ENTITY_META_ATTRIBUTE);
        if ($attributes === []) {
            return [];
        }
        $meta = $attributes[0]->newInstance();

        return [
            'label' => $meta->label ?? null,
            'description' => $meta->description ?? null,
            'group' => $meta->group ?? null,
        ];
    }

    private function relationKey(string $from, string $to, string $name): string
    {
        $pair = [$from, $to];
        sort($pair);

        return $pair[0] . '|' . $pair[1] . '|' . $name;
    }

    private function mermaidType(string $type): string
    {
        // Mermaid attribute types must be a single token.
        return preg_replace('/[^A-Za-z0-9_]/', '_', $type) ?: 'mixed';
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
