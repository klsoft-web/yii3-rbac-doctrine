<?php

namespace Klsoft\Yii3RbacDoctrine;

use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Doctrine\ORM\EntityManagerInterface;
use Klsoft\Yii3RbacDoctrine\Entities\YiiRbacItem;
use Klsoft\Yii3RbacDoctrine\Entities\YiiRbacItemChild;

final class ItemsStorage implements ItemsStorageInterface
{
    /**
     * @param EntityManagerInterface $entityManager The EntityManager instance.
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->entityManager->wrapInTransaction(function ($em) {
            $em->createQueryBuilder()
                ->delete(YiiRbacItemChild::class, 'child')
                ->getQuery()
                ->execute();

            $em->createQueryBuilder()
                ->delete(YiiRbacItem::class, 'item')
                ->getQuery()
                ->execute();
        });
        $this->entityManager->clear();
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->getQuery()
            ->getResult();

        return $this->getItemsIndexedByName($rows);
    }

    /**
     * @inheritDoc
     */
    public function getByNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $rawItems = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.name in (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        return $this->getItemsIndexedByName($rawItems);
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): Permission|Role|null
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : $this->createItem($row);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(item.name)')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function roleExists(string $name): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(item.name)')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.name = :name', 'item.type = :type')
            ->setParameter('name', $name)
            ->setParameter('type', Item::TYPE_ROLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function add(Item $item): void
    {
        $yiiRbacItem = new YiiRbacItem();
        $yiiRbacItem->setName($item->getName());
        $yiiRbacItem->setType($item->getType());
        $yiiRbacItem->setDescription($item->getDescription());
        $yiiRbacItem->setRuleName($item->getRuleName());
        $yiiRbacItem->setCreatedAt($item->getCreatedAt());
        $yiiRbacItem->setUpdatedAt($item->getUpdatedAt());
        $this->entityManager->persist($yiiRbacItem);
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function update(string $name, Item $item): void
    {
        $this->entityManager->wrapInTransaction(function ($em) use ($name, $item) {
            if ($name === $item->getName()) {
                $em->createQueryBuilder()
                    ->update(YiiRbacItem::class, 'item')
                    ->set('item.type', ':type')
                    ->set('item.description', ':description')
                    ->set('item.rule_name', ':rule_name')
                    ->set('item.created_at', ':created_at')
                    ->set('item.updated_at', ':updated_at')
                    ->where('item.name = :name')
                    ->setParameter('type', $item->getType())
                    ->setParameter('description', $item->getDescription())
                    ->setParameter('rule_name', $item->getRuleName())
                    ->setParameter('updated_at', time())
                    ->setParameter('name', $name)
                    ->getQuery()
                    ->execute();
            } else {
                $itemFindByName = $this->entityManager->find(YiiRbacItem::class, $name);
                if ($itemFindByName !== null) {
                    $itemsChildren = $em->createQueryBuilder()
                        ->select('child')
                        ->from(YiiRbacItemChild::class, 'child')
                        ->where('child.parent = :item')
                        ->orWhere('child.child = :item')
                        ->setParameter('item', $itemFindByName)
                        ->getQuery()
                        ->getResult();
                    if ($itemsChildren !== []) {
                        $this->removeRelatedItemsChildren($itemFindByName);
                    }
                    $em->remove($itemFindByName);

                    $time = time();
                    $newItem = new YiiRbacItem();
                    $newItem->setName($item->getName());
                    $newItem->setType($item->getType());
                    $newItem->setDescription($item->getDescription());
                    $newItem->setRuleName($item->getRuleName());
                    $newItem->setCreatedAt($time);
                    $newItem->setUpdatedAt($time);
                    $em->persist($newItem);

                    if ($itemsChildren !== []) {
                        $itemsChildren = array_map(
                            static function (YiiRbacItemChild $itemChild) use ($name, $item, $newItem): YiiRbacItemChild {
                                if ($itemChild->getParent()->getName() === $name) {
                                    $itemChild->setParent($newItem);
                                }

                                if ($itemChild->getChild()->getName() === $name) {
                                    $itemChild->setChild($newItem);
                                }

                                return $itemChild;
                            },
                            $itemsChildren,
                        );

                        foreach ($itemsChildren as $itemsChild) {
                            $yiiRbacItemChild = new YiiRbacItemChild();
                            $yiiRbacItemChild->setParent($itemsChild->getParent());
                            $yiiRbacItemChild->setChild($itemsChild->getChild());
                            $em->persist($yiiRbacItemChild);
                        }
                        $em->flush();
                    }
                }
            }
        });
    }

    /**
     * Removes all related records in items children table for a given item name.
     *
     * @param string $name Item name.
     */
    private function removeRelatedItemsChildren(YiiRbacItem $itemFindByName): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :item')
            ->orWhere('child.child = :item')
            ->setParameter('item', $itemFindByName)
            ->getQuery()
            ->execute();
    }

    /**
     * @inheritDoc
     */
    public function remove(string $name): void
    {
        $this->entityManager->wrapInTransaction(function ($em) use ($name) {
            $itemFindByName = $this->entityManager->find(YiiRbacItem::class, $name);
            if ($itemFindByName !== null) {
                $this->removeRelatedItemsChildren($itemFindByName);
                $em->createQueryBuilder()
                    ->delete(YiiRbacItem::class, 'item')
                    ->where('item.name = :name')
                    ->setParameter('name', $name)
                    ->getQuery()
                    ->execute();
                $this->entityManager->clear();
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function getRoles(): array
    {
        return $this->getItemsByType(Item::TYPE_ROLE);
    }

    /**
     * @inheritDoc
     */
    public function getRolesByNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $rawItems = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.type = :type', 'item.name in (:names)')
            ->setParameter('type', Item::TYPE_ROLE)
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        /** @psalm-var array<string, Role> */
        return $this->getItemsIndexedByName($rawItems);
    }

    /**
     * @inheritDoc
     */
    public function getRole(string $name): ?Role
    {
        return $this->getItemByTypeAndName(Item::TYPE_ROLE, $name);
    }

    /**
     * @inheritDoc
     */
    public function clearRoles(): void
    {
        $this->removeItemsByType(Item::TYPE_ROLE);
    }

    /**
     * @inheritDoc
     */
    public function getPermissions(): array
    {
        return $this->getItemsByType(Item::TYPE_PERMISSION);
    }

    /**
     * @inheritDoc
     */
    public function getPermissionsByNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $rawItems = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.type = :type', 'item.name in (:names)')
            ->setParameter('type', Item::TYPE_PERMISSION)
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();

        /** @psalm-var array<string, Permission> */
        return $this->getItemsIndexedByName($rawItems);
    }

    /**
     * @inheritDoc
     */
    public function getPermission(string $name): ?Permission
    {
        return $this->getItemByTypeAndName(Item::TYPE_PERMISSION, $name);
    }

    /**
     * @inheritDoc
     */
    public function clearPermissions(): void
    {
        $this->removeItemsByType(Item::TYPE_PERMISSION);
    }

    /**
     * @param string $type
     */
    private function removeItemsByType(string $type): void
    {
        foreach ($this->getItemsByType($type) as $item) {
            $this->remove($item->getName());
        }
    }

    /**
     * @inheritDoc
     */
    public function getParents(string $name): array
    {
        $result = [];
        $this->fillParentsRecursive($name, $result);

        return $result;
    }

    /**
     * @param string $name
     * @param array $result
     */
    private function fillParentsRecursive(string $name, array &$result): void
    {
        $queryResult = $this->entityManager->createQueryBuilder()
            ->select('child')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.child = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $name))
            ->getQuery()
            ->getResult();
        foreach ($queryResult as $yiiRbacItemChild) {
            $parentName = $yiiRbacItemChild->getParent()->getName();
            $parent = $this->get($parentName);
            if ($parent !== null) {
                $result[$parentName] = $parent;
            }

            $this->fillParentsRecursive($parentName, $result);
        }
    }

    /**
     * @inheritDoc
     */
    public function getHierarchy(string $name): array
    {
        $item = $this->get($name);
        if ($item !== null) {
            $result = [$name => ['item' => $item, 'children' => []]];
            $this->fillHierarchyRecursive($name, $result);
            return $result;
        }
        return [];
    }

    /**
     * @param string $name
     * @param array $result
     * @param array $addedChildItems
     */
    private function fillHierarchyRecursive(string $name, array &$result, array $addedChildItems = []): void
    {
        $queryResult = $this->entityManager->createQueryBuilder()
            ->select('child')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.child = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $name))
            ->getQuery()
            ->getResult();
        foreach ($queryResult as $yiiRbacItemChild) {
            $parentName = $yiiRbacItemChild->getParent()->getName();
            $parent = $this->get($parentName);
            if ($parent !== null) {
                $result[$parentName]['item'] = $parent;

                $childName = $yiiRbacItemChild->getChild()->getName();
                $child = $this->get($childName);
                if ($child !== null) {
                    $addedChildItems[$childName] = $child;
                    $result[$parentName]['children'] = $addedChildItems;
                }
            }
            $this->fillHierarchyRecursive($parentName, $result, $addedChildItems);
        }
    }

    /**
     * @inheritDoc
     */
    public function getDirectChildren(string $name): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('child')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $name))
            ->getQuery()
            ->getResult();

        $rawItems = [];
        foreach ($result as $yiiRbacItemChild) {
            $child = $yiiRbacItemChild->getChild();
            $rawItems[] = [
                'name' => $child->getName(),
                'type' => $child->getType(),
                'description' => $child->getDescription(),
                'rule_name' => $child->getRuleName(),
                'created_at' => $child->getCreatedAt(),
                'updated_at' => $child->getUpdatedAt()];
        }

        return $this->getItemsIndexedByName($rawItems);
    }

    /**
     * @inheritDoc
     */
    public function getAllChildren(string|array $names): array
    {
        $result = [];
        $this->getAllChildrenInternal($names, $result);

        return $result;
    }

    /**
     * @param string|array $names
     * @param array $result
     */
    private function getAllChildrenInternal(string|array $names, array &$result): void
    {
        $names = (array)$names;
        foreach ($names as $name) {
            $this->fillChildrenRecursive($name, $result, $names);
        }
    }

    /**
     * @param string $name
     * @param array $result
     * @param array $baseNames
     */
    private function fillChildrenRecursive(string $name, array &$result, array $baseNames): void
    {
        $queryResult = $this->entityManager->createQueryBuilder()
            ->select('child')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $name))
            ->getQuery()
            ->getResult();
        foreach ($queryResult as $yiiRbacItemChild) {
            $childName = $yiiRbacItemChild->getChild()->getName();
            if (in_array($childName, $baseNames, strict: true)) {
                continue;
            }

            $child = $this->get($childName);
            if ($child !== null) {
                $result[$childName] = $child;
            }

            $this->fillChildrenRecursive($childName, $result, $baseNames);
        }
    }

    /**
     * @inheritDoc
     */
    public function getAllChildPermissions(string|array $names): array
    {
        $result = [];
        $this->getAllChildrenInternal($names, $result);

        return $this->filterPermissions($result);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private function filterPermissions(array $items): array
    {
        return array_filter($items, static fn(Permission|Role $item): bool => $item instanceof Permission);
    }

    /**
     * @inheritDoc
     */
    public function getAllChildRoles(string|array $names): array
    {
        $result = [];
        $this->getAllChildrenInternal($names, $result);

        return $this->filterRoles($result);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    private function filterRoles(array $items): array
    {
        return array_filter($items, static fn(Permission|Role $item): bool => $item instanceof Role);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(string $name): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(child.parent)')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $name))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function hasChild(string $parentName, string $childName): bool
    {
        if ($parentName === $childName) {
            return true;
        }

        $children = $this->getDirectChildren($parentName);
        if (empty($children)) {
            return false;
        }

        foreach ($children as $groupChild) {
            if ($this->hasChild($groupChild->getName(), $childName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasDirectChild(string $parentName, string $childName): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(child.parent)')
            ->from(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :parent', 'child.child = :child')
            ->setParameter('parent', $this->entityManager->find(YiiRbacItem::class, $parentName))
            ->setParameter('child', $this->entityManager->find(YiiRbacItem::class, $childName))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function addChild(string $parentName, string $childName): void
    {
        $yiiRbacItemChild = new YiiRbacItemChild();
        $yiiRbacItemChild->setParent($this->entityManager->find(YiiRbacItem::class, $parentName));
        $yiiRbacItemChild->setChild($this->entityManager->find(YiiRbacItem::class, $childName));
        $this->entityManager->persist($yiiRbacItemChild);
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function removeChild(string $parentName, string $childName): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :parent', 'child.child = :child')
            ->setParameter('parent', $this->entityManager->find(YiiRbacItem::class, $parentName))
            ->setParameter('child', $this->entityManager->find(YiiRbacItem::class, $childName))
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }

    /**
     * @inheritDoc
     */
    public function removeChildren(string $parentName): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacItemChild::class, 'child')
            ->where('child.parent = :item')
            ->setParameter('item', $this->entityManager->find(YiiRbacItem::class, $parentName))
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }

    /**
     * Gets either all existing roles or permissions, depending on a specified type.
     *
     * @param string $type Either {@see Item::TYPE_ROLE} or {@see Item::TYPE_PERMISSION}.
     *
     * @return array A list of roles / permissions.
     */
    private function getItemsByType(string $type): array
    {
        $rawItems = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();

        return $this->getItemsIndexedByName($rawItems);
    }

    /**
     * Gets a single item by its type and name.
     *
     * @param string $type Either {@see Item::TYPE_ROLE} or {@see Item::TYPE_PERMISSION}.
     * @param string $name
     *
     * @return Permission|Role|null Either role or permission, depending on an initial type specified. `null` is
     * returned when no item was found by given condition.
     */
    private function getItemByTypeAndName(string $type, string $name): Permission|Role|null
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('item.name',
                'item.type',
                'item.description',
                'item.rule_name',
                'item.created_at',
                'item.updated_at')
            ->from(YiiRbacItem::class, 'item')
            ->where('item.type = :type', 'item.name = :name')
            ->setParameter('type', $type)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : $this->createItem($row);
    }

    /**
     * A factory method for creating single item with all attributes filled.
     *
     * @param array $rawItem
     *
     * @return Permission|Role Either role or permission, depending on an initial type specified.
     */
    private function createItem(array $rawItem): Permission|Role
    {
        $item = $this
            ->createItemByTypeAndName($rawItem['type'], $rawItem['name'])
            ->withCreatedAt((int)$rawItem['created_at'])
            ->withUpdatedAt((int)$rawItem['updated_at']);

        if ($rawItem['description'] !== null) {
            $item = $item->withDescription($rawItem['description']);
        }

        if ($rawItem['rule_name'] !== null) {
            $item = $item->withRuleName($rawItem['rule_name']);
        }

        return $item;
    }

    /**
     * A basic factory method for creating a single item with name only.
     *
     * @param string $type Either {@see Item::TYPE_ROLE} or {@see Item::TYPE_PERMISSION}.
     * @param string $name
     *
     * @return Permission|Role Either role or permission, depending on an initial type specified.
     */
    private function createItemByTypeAndName(string $type, string $name): Permission|Role
    {
        return $type === Item::TYPE_PERMISSION ? new Permission($name) : new Role($name);
    }

    /**
     * @param array $rawItems
     *
     * @return array
     */
    private function getItemsIndexedByName(array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $rawItem) {
            $items[$rawItem['name']] = $this->createItem($rawItem);
        }

        return $items;
    }
}
