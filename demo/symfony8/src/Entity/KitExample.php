<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demo entity: table with all supported column types and options (second migration).
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE_NAME)]
class KitExample
{
    public const TABLE_NAME = 'kit_example';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false, options: ['default' => 0])]
    private int $colSmallint = 0;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $colBigint = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => true])]
    private bool $colBoolean = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $colDecimal = '0.00';

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $colFloat = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $colString = '';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $colStringNullable = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $colText = null;

    #[ORM\Column(type: Types::ASCII_STRING, length: 64, nullable: true)]
    private ?string $colAscii = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $colDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $colDatetimeImmutable = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $colDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $colTime = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $colJson = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private ?string $colBlob = null;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    private ?string $colGuid = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => 'Example comment'])]
    private ?string $colComment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColSmallint(): int
    {
        return $this->colSmallint;
    }

    public function setColSmallint(int $colSmallint): static
    {
        $this->colSmallint = $colSmallint;
        return $this;
    }

    public function getColBigint(): ?string
    {
        return $this->colBigint;
    }

    public function setColBigint(?string $colBigint): static
    {
        $this->colBigint = $colBigint;
        return $this;
    }

    public function isColBoolean(): bool
    {
        return $this->colBoolean;
    }

    public function setColBoolean(bool $colBoolean): static
    {
        $this->colBoolean = $colBoolean;
        return $this;
    }

    public function getColDecimal(): string
    {
        return $this->colDecimal;
    }

    public function setColDecimal(string $colDecimal): static
    {
        $this->colDecimal = $colDecimal;
        return $this;
    }

    public function getColFloat(): ?float
    {
        return $this->colFloat;
    }

    public function setColFloat(?float $colFloat): static
    {
        $this->colFloat = $colFloat;
        return $this;
    }

    public function getColString(): string
    {
        return $this->colString;
    }

    public function setColString(string $colString): static
    {
        $this->colString = $colString;
        return $this;
    }

    public function getColStringNullable(): ?string
    {
        return $this->colStringNullable;
    }

    public function setColStringNullable(?string $colStringNullable): static
    {
        $this->colStringNullable = $colStringNullable;
        return $this;
    }

    public function getColText(): ?string
    {
        return $this->colText;
    }

    public function setColText(?string $colText): static
    {
        $this->colText = $colText;
        return $this;
    }

    public function getColAscii(): ?string
    {
        return $this->colAscii;
    }

    public function setColAscii(?string $colAscii): static
    {
        $this->colAscii = $colAscii;
        return $this;
    }

    public function getColDatetime(): ?\DateTime
    {
        return $this->colDatetime;
    }

    public function setColDatetime(?\DateTime $colDatetime): static
    {
        $this->colDatetime = $colDatetime;
        return $this;
    }

    public function getColDatetimeImmutable(): ?\DateTimeImmutable
    {
        return $this->colDatetimeImmutable;
    }

    public function setColDatetimeImmutable(?\DateTimeImmutable $colDatetimeImmutable): static
    {
        $this->colDatetimeImmutable = $colDatetimeImmutable;
        return $this;
    }

    public function getColDate(): ?\DateTimeInterface
    {
        return $this->colDate;
    }

    public function setColDate(?\DateTimeInterface $colDate): static
    {
        $this->colDate = $colDate;
        return $this;
    }

    public function getColTime(): ?\DateTimeInterface
    {
        return $this->colTime;
    }

    public function setColTime(?\DateTimeInterface $colTime): static
    {
        $this->colTime = $colTime;
        return $this;
    }

    public function getColJson(): ?array
    {
        return $this->colJson;
    }

    public function setColJson(?array $colJson): static
    {
        $this->colJson = $colJson;
        return $this;
    }

    public function getColBlob(): ?string
    {
        return $this->colBlob;
    }

    public function setColBlob(?string $colBlob): static
    {
        $this->colBlob = $colBlob;
        return $this;
    }

    public function getColGuid(): ?string
    {
        return $this->colGuid;
    }

    public function setColGuid(?string $colGuid): static
    {
        $this->colGuid = $colGuid;
        return $this;
    }

    public function getColComment(): ?string
    {
        return $this->colComment;
    }

    public function setColComment(?string $colComment): static
    {
        $this->colComment = $colComment;
        return $this;
    }
}
