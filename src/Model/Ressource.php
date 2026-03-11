<?php

namespace App\Model;

class Ressource {

    public const string DEFAULT_PREFIX = 'OK';

    private string $name;
    
    public function __construct(string $name, private string $entityClass, private string $idPrefix, private bool $needSequence = false)
    {
        $this->name = \strtolower($name);
    }

    public static function new(string $name, string $entityClass, string $idPrefix, bool $needSequence = false): static {
        return new Ressource($name, $entityClass, $idPrefix, $needSequence);
    }

    /**
     * Get the value of name
     */ 
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the value of entityClass
     */ 
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get the value of idPrefix
     */ 
    public function getIdPrefix(): string
    {
        return $this->idPrefix;
    }

    /**
     * Get the value of needSequence
     */ 
    public function isSequenceNeeded()
    {
        return $this->needSequence;
    }

    public function getSerialName():string {
        return static::buildSerialName($this->idPrefix);
    }

    public static function buildSerialName(string $prefix): string {
        return strtolower("res_{$prefix}_serial");
    }
}