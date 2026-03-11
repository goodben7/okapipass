<?php
namespace App\Message;

use App\Entity\User;

class UserActivityLoggedMessage
{
    public function __construct(
        private string             $user,
        private \DateTimeImmutable $date,
        private string             $activity,
        private string             $ressourceName,
        private User               $triggeredBy,
        private ?string            $ressourceIdentifier = null,
        private ?string            $activityDescription = null,
    )
    {
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getActivity(): string
    {
        return $this->activity;
    }

    public function getRessourceName(): string
    {
        return $this->ressourceName;
    }

    public function getRessourceIdentifier(): ?string
    {
        return $this->ressourceIdentifier;
    }

    /**
    * Get the value of activityDescription
    */ 
    public function getActivityDescription(): string|null
    {
        return $this->activityDescription;
    }

    /**
    * Get the value of triggeredBy
    */ 
    public function getTriggeredBy(): User
    {
        return $this->triggeredBy;
    }
}
