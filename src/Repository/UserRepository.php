<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method void      save(User $user)
 * @method User[]    findAllActiveCreatedTheLastWeek()
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function findAllActiveCreatedTheLastWeek(): array
    {
        $date = new \DateTime();
        $date->modify('-7 days');

        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt > :date')
            ->andWhere('u.active = true')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}
