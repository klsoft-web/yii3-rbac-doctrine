# YII3-RBAC-DOCTRINE

The package provides [Yii RBAC](https://github.com/yiisoft/rbac) storage using the [Doctrine ORM](https://www.doctrine-project.org/).

## Requirement

 - PHP 8.2 or higher.

## Installation

```bash
composer require klsoft/yii3-rbac-doctrine
```

## How to use

### 1. [Configure](https://github.com/klsoft-web/yii3-doctrine?tab=readme-ov-file#1-configure-the-entitymanagerinterface) the EntityManagerInterface.

### 2. Configure the CurrentUser.

```php  
use Yiisoft\User\CurrentUser;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Access\AccessCheckerInterface;

return [
    // ...
    CurrentUser::class => [  
        'withSession()' => [Reference::to(SessionInterface::class)],  
        'withAccessChecker()' => [Reference::to(AccessCheckerInterface::class)]  
    ],
];
```

### 3. Use the Doctrine console command to create or update the database schema.

Create the database schema:

```bash
./yii doctrine:orm:schema-tool:create
```

Update the database schema:

```bash
./yii doctrine:orm:schema-tool:update --force 
```
