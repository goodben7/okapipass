<?php

declare(strict_types=1);

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Profile;
use App\Entity\User;
use App\Model\Ressource;

return static function (): iterable {

    yield Ressource::new("user", User::class, "US", true);
    yield Ressource::new("profile", Profile::class, "PR", true);
    yield Ressource::new("checkpoint", Checkpoint::class, "CP", true);
    yield Ressource::new("gopass", GoPass::class, "GP", true);

};
