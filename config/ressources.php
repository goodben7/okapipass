<?php

declare(strict_types=1);

use App\Entity\Checkpoint;
use App\Entity\Agency;
use App\Entity\GoPass;
use App\Entity\Payment;
use App\Entity\Profile;
use App\Entity\Ticket;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Hotel;
use App\Entity\TouristSite;
use App\Model\Ressource;

return static function (): iterable {

    yield Ressource::new("user", User::class, "US", true);
    yield Ressource::new("profile", Profile::class, "PR", true);
    yield Ressource::new("checkpoint", Checkpoint::class, "CP", true);
    yield Ressource::new("agency", Agency::class, "AG", true);
    yield Ressource::new("gopass", GoPass::class, "GP", true);
    yield Ressource::new("ticket", Ticket::class, "TI", true);
    yield Ressource::new("payment", Payment::class, "PA", true);
    yield Ressource::new("trip", Trip::class, "TR", true);
    yield Ressource::new("hotel", Hotel::class, "HO", true);
    yield Ressource::new("tourist_site", TouristSite::class, "TS", true);

};
