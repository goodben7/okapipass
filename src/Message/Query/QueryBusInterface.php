<?php

namespace App\Message\Query;

interface QueryBusInterface {
    public function ask(QueryInterface $query): mixed;
}