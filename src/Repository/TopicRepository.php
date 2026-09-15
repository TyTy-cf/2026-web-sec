<?php

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Topic>
 */
class TopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }

    /**
     * @return Topic[] Returns an array of Topic objects
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.title LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Topic[] Returns an array of Topic objects
     */
    public function LastByCreatedAt(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.category', 'c')
            ->join('t.author', 'u')
            ->select('t', 'c', 'u')
            ->orderBy('t.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

}
