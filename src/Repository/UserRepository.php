<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }
    
    public function checkFolderExists($fileName)
    {
        $qb = $this->_em->createQueryBuilder();
    
        $qb->select( 'u')
            ->from(User::class, 'u')
            ->where($qb->expr()->eq('u.directoryName', '?1'))
            ->setParameter(1, $fileName);
        $query = $qb->getQuery();
        return $query->getSingleResult();
    }

}
