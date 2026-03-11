<?php

namespace App\Model;


use App\Model\Ressource;

abstract class RessourceRepository {

    /**
     * @return array<string,\App\Model\Ressource>
     */
    abstract public function getAllRessources(): iterable;

    public function getPrefixFor(string $ressourceClass): string {
        $list = $this->getAllRessources();

        if (isset($list[$ressourceClass])) {
            return $list[$ressourceClass]->getIdPrefix();
        }

        return Ressource::DEFAULT_PREFIX;
    }

    public function getRessourceSerialName(string $ressourceClass): string {
        $list = $this->getAllRessources();

        if (isset($list[$ressourceClass])) {
            return $list[$ressourceClass]->getSerialName();
        }

        return Ressource::buildSerialName(Ressource::DEFAULT_PREFIX);
    }

    public function getClassForRessourceNamed(string $name): string {
        foreach ($this->getAllRessources() as $class => $ressource) {
            if ($ressource->getName() === strtolower($name)) {
                return $class;
            }
        }

        throw new \InvalidArgumentException(sprintf('cannot find ressource named %s', $name));
    }

    public function getRessourceByName(string $name): ?Ressource
    {
        $list = $this->getAllRessources();

        if (isset($list[$name]))
            return $list[$name];

        return null;
    }

    abstract public function isRessourceExist(string $id, string $name): bool;

    abstract public function fetch(string $id, string $name): mixed;

}
