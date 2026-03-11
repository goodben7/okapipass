<?php
namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;
use App\Model\RessourceRepository as RepositoryRessourceRepository;

class RessourceRepository extends RepositoryRessourceRepository {

    private ?string $projectDir = null;

    public function __construct(private EntityManagerInterface $em)
    {
        if (null === $this->projectDir) {
            $r = new \ReflectionObject($this);

            if (!is_file($dir = $r->getFileName())) {
                throw new \LogicException(sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name));
            }

            $dir = $rootDir = \dirname($dir);
            while (!is_file($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    $this->projectDir = $rootDir;
                    break;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }
    }

    public function getAllRessources(): iterable
    {
        $fx = require sprintf('%s/config/ressources.php', $this->projectDir);
        $raw = $fx();

        $list = [];
        /** @var \App\Model\Ressource $ressource */
        foreach ($raw as $ressource) {
            $list[$ressource->getEntityClass()] = $ressource;
        }

        return $list;
    }

    public function isRessourceExist(string $id, string $name): bool {
        $classname = $this->getClassForRessourceNamed($name);

        return null !== $this->em->find($classname, $id);
    }

    public function fetch(string $id, string $name): mixed
    {
        $classname = $this->getClassForRessourceNamed($name);

        return $this->em->find($classname, $id);
    }
}