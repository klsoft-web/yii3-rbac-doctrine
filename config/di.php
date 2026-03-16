<?php

declare(strict_types=1);

use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Rbac\ManagerInterface;
use Klsoft\Yii3RbacDoctrine\ItemsStorage;
use Klsoft\Yii3RbacDoctrine\AssignmentsStorage;

return [
    ItemsStorageInterface::class => ItemsStorage::class,
    AssignmentsStorageInterface::class => AssignmentsStorage::class,
    AccessCheckerInterface::class => ManagerInterface::class,
];
