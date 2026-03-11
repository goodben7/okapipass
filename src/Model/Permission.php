<?php

namespace App\Model;

class Permission {

    private ?string $description;

    public function __construct(private string $permissionId, private string $label)
    {
        
    }

    public static function new(string $permissionId, string $label):static {
        return new self($permissionId, $label);
    }

    /**
     * Get the value of description
     */ 
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */ 
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the value of permissionId
     */ 
    public function getPermissionId(): string
    {
        return $this->permissionId;
    }

    /**
     * Get the value of label
     */ 
    public function getLabel(): string
    {
        return $this->label;
    }
}