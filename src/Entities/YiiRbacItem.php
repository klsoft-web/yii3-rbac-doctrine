<?php

namespace Klsoft\Yii3RbacDoctrine\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yii_rbac_item')]
#[ORM\Index(name: 'idx_yii_rbac_item_type', columns: ['type'])]
class YiiRbacItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 126)]
    private string $name;

    #[ORM\Column(type: 'string', length: 10)]
    private string $type;

    #[ORM\Column(type: 'string', length: 191, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $rule_name;

    #[ORM\Column(type: 'integer')]
    private int $created_at;

    #[ORM\Column(type: 'integer')]
    private int $updated_at;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getRuleName(): ?string
    {
        return $this->rule_name;
    }

    /**
     * @param string|null $ruleName
     */
    public function setRuleName(?string $ruleName): void
    {
        $this->rule_name = $ruleName;
    }

    /**
     * @return int
     */
    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    /**
     * @param int $createdAt
     */
    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    /**
     * @return int
     */
    public function getUpdatedAt(): int
    {
        return $this->updated_at;
    }

    /**
     * @param int $updatedAt
     */
    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }
}
