<?php

namespace Klsoft\Yii3RbacDoctrine;

use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Klsoft\Yii3RbacDoctrine\Entities\YiiRbacAssigment;

final class AssignmentsStorage implements AssignmentsStorageInterface
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
    public function getAll(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('assigment.user_id',
                'assigment.item_name',
                'assigment.created_at')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->getQuery()
            ->getResult();

        $assignments = [];
        foreach ($rows as $row) {
            $assignments[$row['user_id']][$row['item_name']] = new Assignment(
                $row['user_id'],
                $row['item_name'],
                (int)$row['created_at'],
            );
        }

        return $assignments;
    }

    /**
     * @inheritDoc
     */
    public function getByUserId(string $userId): array
    {
        $rawAssignments = $this->entityManager->createQueryBuilder()
            ->select('assigment.item_name',
                'assigment.created_at',
                'assigment.created_at')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.user_id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
        $assignments = [];
        foreach ($rawAssignments as $rawAssignment) {
            $assignments[$rawAssignment['item_name']] = new Assignment(
                $userId,
                $rawAssignment['item_name'],
                (int)$rawAssignment['created_at'],
            );
        }

        return $assignments;
    }

    /**
     * @inheritDoc
     */
    public function getByItemNames(array $itemNames): array
    {
        if (empty($itemNames)) {
            return [];
        }

        $rawAssignments = $this->entityManager->createQueryBuilder()
            ->select('assigment.item_name',
                'assigment.created_at',
                'assigment.created_at')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name in (:itemNames)')
            ->setParameter('itemNames', $itemNames)
            ->getQuery()
            ->getResult();
        $assignments = [];
        foreach ($rawAssignments as $rawAssignment) {
            $assignments[] = new Assignment(
                $rawAssignment['user_id'],
                $rawAssignment['item_name'],
                (int)$rawAssignment['created_at'],
            );
        }

        return $assignments;
    }

    /**
     * @inheritDoc
     */
    public function get(string $itemName, string $userId): ?Assignment
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('assigment.created_at')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName', 'assigment.user_id = :userId')
            ->setParameter('itemName', $itemName)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : new Assignment($userId, $itemName, (int)$row['created_at']);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $itemName, string $userId): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(assigment.item_name)')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName', 'assigment.user_id = :userId')
            ->setParameter('itemName', $itemName)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function userHasItem(string $userId, array $itemNames): bool
    {
        if (empty($itemNames)) {
            return false;
        }

        return $this->entityManager->createQueryBuilder()
            ->select('count(assigment.item_name)')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.user_id = :userId', 'assigment.item_name in (:itemNames)')
            ->setParameter('userId', $userId)
            ->setParameter('itemNames', $itemNames)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function filterUserItemNames(string $userId, array $itemNames): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('assigment.item_name')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.user_id = :userId', 'assigment.item_name in (:itemNames)')
            ->setParameter('userId', $userId)
            ->setParameter('itemNames', $itemNames)
            ->getQuery()
            ->getResult();

        return array_column($rows, 'item_name');
    }

    /**
     * @inheritDoc
     */
    public function add(Assignment $assignment): void
    {
        $yiiRbacAssigment = new YiiRbacAssigment();
        $yiiRbacAssigment->setItemName($assignment->getItemName());
        $yiiRbacAssigment->setUserId($assignment->getUserId());
        $yiiRbacAssigment->setCreatedAt($assignment->getCreatedAt());
        $this->entityManager->persist($yiiRbacAssigment);
        $this->entityManager->flush();
    }

    /**
     * @inheritDoc
     */
    public function hasItem(string $name): bool
    {
        return $this->entityManager->createQueryBuilder()
            ->select('count(assigment.item_name)')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName')
            ->setParameter('itemName', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function renameItem(string $oldName, string $newName): void
    {
        $yiiRbacAssigment = $this->entityManager->createQueryBuilder()
            ->select('assigment')
            ->from(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName')
            ->setParameter('itemName', $oldName)
            ->getQuery()
            ->getOneOrNullResult();
        if ($yiiRbacAssigment != null) {
            $yiiRbacAssigment->setItemName($newName);
            $this->entityManager->persist($yiiRbacAssigment);
            $this->entityManager->flush();
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $itemName, string $userId): void
    {
        $yiiRbacAssigment = $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName', 'assigment.user_id = :userId')
            ->setParameter('itemName', $itemName)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }

    /**
     * @inheritDoc
     */
    public function removeByUserId(string $userId): void
    {
        $yiiRbacAssigment = $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.user_id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }

    /**
     * @inheritDoc
     */
    public function removeByItemName(string $itemName): void
    {
        $yiiRbacAssigment = $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacAssigment::class, 'assigment')
            ->where('assigment.item_name = :itemName')
            ->setParameter('itemName', $itemName)
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(YiiRbacAssigment::class, 'assigment')
            ->getQuery()
            ->execute();
        $this->entityManager->clear();
    }
}
