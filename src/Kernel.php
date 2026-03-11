<?php

namespace App;

use App\Message\Query\QueryHandlerInterface;
use App\Message\Command\CommandHandlerInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    protected function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(QueryHandlerInterface::class)
            ->addTag('messenger.message_handler', ['bus' => 'query.bus'])
        ;

        $container->registerForAutoconfiguration(CommandHandlerInterface::class)
            ->addTag('messenger.message_handler', ['bus' => 'command.bus'])
        ;
    }
}