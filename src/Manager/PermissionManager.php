<?php

namespace App\Manager;

use App\Model\Permission;

class PermissionManager
{
    private static ?PermissionManager $instance = null;
    private ?string $projectDir = null;

    public function __construct()
    {
        $this->detectProjectDir();
    }

    private function detectProjectDir(): void
    {
        if (null !== $this->projectDir) {
            return;
        }

        $r = new \ReflectionObject($this);

        if (!is_file($dir = $r->getFileName())) {
            throw new \LogicException(sprintf('Cannot auto-detect project dir for class "%s".', $r->getName()));
        }

        $dir = $rootDir = \dirname($dir);
        while (!is_file($dir . '/composer.json')) {
            if ($dir === \dirname($dir)) {
                $this->projectDir = $rootDir;
                return;
            }
            $dir = \dirname($dir);
        }
        
        $this->projectDir = $dir;
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array<Permission>
     */
    public function getPermissions(): array
    {
        $configFile = sprintf('%s/config/permissions.php', $this->projectDir);
        
        if (!file_exists($configFile)) {
            throw new \RuntimeException(sprintf('Le fichier de configuration des permissions n\'existe pas: %s', $configFile));
        }
        
        $list = require $configFile;

        // Convertir le Generator en array
        return iterator_to_array($list());
    }

    /**
     * @return array<string, string> Tableau associatif [label => permissionId]
     */
    public function getPermissionsAsListChoices(): array
    {
        $choices = [];
        
        /** @var Permission $permission */
        foreach ($this->getPermissions() as $permission) {
            $choices[$permission->getLabel()] = $permission->getPermissionId();
        }

        return $choices;
    }
}