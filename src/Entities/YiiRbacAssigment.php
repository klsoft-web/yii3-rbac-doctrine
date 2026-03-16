<?php

namespace Klsoft\Yii3RbacDoctrine\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yii_rbac_assignment')]
class YiiRbacAssigment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 126)]
    private string $item_name;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 126)]
    private string $user_id;

    #[ORM\Column(type: 'integer')]
    private int $created_at;

    /**
     * @param string $itemNname
     */
    public function setItemName(string $itemNname): void
    {
        $this->item_name = $itemNname;
    }

    /**
     * @param string $userId
     */
    public function setUserId(string $userId): void
    {
        $this->user_id = $userId;
    }

    /**
     * @param string $createdAt
     */
    public function setCreatedAt(string $createdAt): void
    {
        $this->created_at = $createdAt;
    }
}
