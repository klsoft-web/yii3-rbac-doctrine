<?php

namespace Klsoft\Yii3RbacDoctrine\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yii_rbac_item_child')]
class YiiRbacItemChild
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: YiiRbacItem::class)]
    #[ORM\JoinColumn(name: 'parent', referencedColumnName: 'name')]
    private YiiRbacItem $parent;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: YiiRbacItem::class)]
    #[ORM\JoinColumn(name: 'child', referencedColumnName: 'name')]
    private YiiRbacItem $child;

    /**
     * @return YiiRbacItem
     */
    public function getParent(): YiiRbacItem
    {
        return $this->parent;
    }

    /**
     * @param YiiRbacItem $parent
     */
    public function setParent(YiiRbacItem $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return YiiRbacItem
     */
    public function getChild(): YiiRbacItem
    {
        return $this->child;
    }

    /**
     * @param YiiRbacItem $child
     */
    public function setChild(YiiRbacItem $child): void
    {
        $this->child = $child;
    }
}
