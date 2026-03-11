<?php
namespace App\Message\Command;

interface CommandBusInterface {

    public function dispatch(CommandInterface $command): mixed;
}