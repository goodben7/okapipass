<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Find documents by holder type and holder id
     * 
     * @return Document[] Returns an array of Document objects
     */
    public function findByHolder(string $holderType, string $holderId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.holderType = :holderType')
            ->andWhere('d.holderId = :holderId')
            ->setParameter('holderType', $holderType)
            ->setParameter('holderId', $holderId)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents by type
     * 
     * @return Document[] Returns an array of Document objects
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.type = :type')
            ->setParameter('type', $type)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find documents by title (partial match)
     * 
     * @return Document[] Returns an array of Document objects
     */
    public function findByTitle(string $title): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.title LIKE :title')
            ->setParameter('title', '%' . $title . '%')
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}