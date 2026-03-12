<?php

declare(strict_types=1);

use App\Entity\Checkpoint;
use App\Entity\Agency;
use App\Entity\GoPass;
use App\Entity\Payment;
use App\Entity\Profile;
use App\Entity\Ticket;
use App\Entity\User;
use App\Model\Ressource;

return static function (): iterable {

    yield Ressource::new("user", User::class, "US", true);
    yield Ressource::new("profile", Profile::class, "PR", true);
    yield Ressource::new("checkpoint", Checkpoint::class, "CP", true);
    yield Ressource::new("agency", Agency::class, "AG", true);
    yield Ressource::new("gopass", GoPass::class, "GP", true);
    yield Ressource::new("ticket", Ticket::class, "TI", true);
    yield Ressource::new("payment", Payment::class, "PA", true);

};
