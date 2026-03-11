<?php
namespace App\Event;

use App\Model\RessourceInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ActivityEvent extends Event {

    public const  string ACTION_CREATE = 'created';
    public const  string ACTION_VIEW = 'viewed';
    public const  string ACTION_LIST = 'listed';
    public const  string ACTION_EDIT = 'edited';
    public const  string ACTION_DELETE = 'deleted';

    private ?string $ressourceClass;
    private ?string $activityDescription;

    public function __construct(private ?RessourceInterface $ressource, private string $activity, ?string $ressourceClass = null, ?string $activityDescription = null)
    {
        $this->ressourceClass = $ressourceClass;
        $this->activityDescription = $activityDescription;

        if (null !== $ressource) {
            $this->ressourceClass = get_class($ressource);
        }

        if (!$this->ressourceClass) {
            throw new \InvalidArgumentException("ressource class name must be specified");
        }
    }

    /**
     * Get the value of ressource
     */ 
    public function getRessource(): ?RessourceInterface
    {
        return $this->ressource;
    }

    /**
     * Get the value of activity
     */ 
    public function getActivity(): string
    {
        return $this->activity;
    }

    /**
     * Get the value of ressourceClass
     */ 
    public function getRessourceClass(): string
    {
        return $this->ressourceClass;
    }

    public static function getEventName(string $ressourceFqcn, string $action): string {
        return sprintf('app.%s.%s', strtolower(str_replace('\\', '_', $ressourceFqcn)), $action);
    }

    /**
     * Get the value of activityDescription
     */ 
    public function getActivityDescription(): string|null
    {
        return $this->activityDescription;
    }
}