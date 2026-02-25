<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
class KitItem
{
    public const TABLE_NAME = 'kit_item';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: KitUser::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?KitUser $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?KitUser
    {
        return $this->user;
    }

    public function setUser(?KitUser $user): static
    {
        $this->user = $user;
        return $this;
    }
}
