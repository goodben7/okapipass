<?php
namespace App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

/**
 * Custom ID generator for entities
 * 
 * Generates IDs in format: PREFIX + RANDOM_LETTERS + DATE_TIME
 * Each entity using this generator must define an ID_PREFIX constant
 */
class IdGenerator extends AbstractIdGenerator 
{
    /**
     * Characters used for generating random letters
     */
    private const string CHARACTERS = 'abcdefghijklmnopqrstuvwxyz';
    
    /**
     * Default length for random letters part
     */
    private const int DEFAULT_RANDOM_LENGTH = 4;
    
    /**
     * DateTime format used in ID generation
     */
    private const string DATETIME_FORMAT = 'mdHis';
    
    /**
     * Generates a unique ID for an entity
     * 
     * @param EntityManagerInterface $em The entity manager
     * @param object|null $entity The entity for which to generate an ID
     * @return mixed The generated ID
     * @throws \LogicException If entity doesn't have ID_PREFIX constant
     */
    public function generateId(EntityManagerInterface $em, object|null $entity): mixed
    {
        if (!\defined(\get_class($entity) . '::ID_PREFIX')) {
            throw new \LogicException(\sprintf('Entity %s must define an ID_PREFIX constant', \get_class($entity)));
        }
        
        $currentDateTime = new \DateTime();
        $dateTimeString = $currentDateTime->format(self::DATETIME_FORMAT);
        $randomLetters = $this->generateRandomLetters(self::DEFAULT_RANDOM_LENGTH);
        
        return $entity::ID_PREFIX . \strtoupper($randomLetters . $dateTimeString);
    }

    /**
     * Generates a string of random letters
     * 
     * @param int $length The length of the random string
     * @return string The generated random string
     */
    private function generateRandomLetters(int $length): string
    {
        $randomLetters = '';
        $charactersLength = \strlen(self::CHARACTERS) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $randomLetters .= self::CHARACTERS[\random_int(0, $charactersLength)];
        }
        
        return $randomLetters;
    }
}