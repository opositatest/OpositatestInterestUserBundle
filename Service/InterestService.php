<?php
namespace Opositatest\InterestUserBundle\Service;

use Doctrine\ORM\EntityManager;
use Opositatest\InterestUserBundle\Entity\FollowInterestUser;
use Opositatest\InterestUserBundle\Entity\Interest;
use Opositatest\InterestUserBundle\Entity\UnFollowInterestUser;
use Opositatest\InterestUserBundle\Model\UserTrait;
use Opositatest\InterestUserBundle\Repository\InterestRepository;
use Opositatest\InterestUserBundle\Repository\UserInterestRepository;

class InterestService {
    const FOLLOW_INTEREST = "followInterest";
    const UNFOLLOW_INTEREST = "unfollowInterest";

    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Add interests with recursive for children
     *
     * @param Interest $interest
     * @param $user
     * @param string $followMode
     * @param bool $flush
     * @param bool $blockBased
     * @param bool $recursive
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function postInterestUserRecursiveChildren(Interest $interest, $user, $followMode = self::FOLLOW_INTEREST, $flush = false, $blockBased = false, $recursive = false) {
        if ($blockBased == null) {
            $blockBased = 0;
        }
        $done = $this->postInterestUser($interest, $user, $followMode, $flush, $recursive, $blockBased);

        if($recursive) {
            foreach($interest->getChildren() as $child) {
                $this->postInterestUserRecursiveChildren($child, $user, $followMode, $flush, $blockBased);
            }
        }
        return $done;
    }

    /**
     * Remove interests with recursive for children
     *
     * @param Interest $interest
     * @param $user
     * @param string $followMode
     * @param bool $flush
     * @param bool $recursive
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deleteInterestUserRecursiveChildren(Interest $interest, $user, $followMode = self::FOLLOW_INTEREST, $flush = false, $recursive = false) {
        $done = $this->deleteInterestUser($interest, $user, $followMode, $flush);
        if($recursive) {
            foreach($interest->getChildren() as $child) {
                $this->deleteInterestUserRecursiveChildren($child, $user, $followMode, $flush);
            }
        }
        return $done;
    }

    /**
     * Add Interest to User
     *
     * @param Interest $interest
     * @param $user
     * @param string $followMode - by default: "followInterest"
     * @param bool $flush
     * @param bool $includeEntityRecursively
     * @param bool $blockBased
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function postInterestUser(Interest $interest, $user, $followMode = self::FOLLOW_INTEREST, $flush = false, $includeEntityRecursively = false, $blockBased = false) {
        /** @var UserTrait $user */
        $done = false;

        if (null === $user) {
            return $done;
        }

        if ($followMode == null || ($followMode != self::FOLLOW_INTEREST && $followMode != self::UNFOLLOW_INTEREST)) {
            $followMode = self::FOLLOW_INTEREST;
        }
        if ($followMode == self::FOLLOW_INTEREST) {
            if ($interest != null && !$user->existFollowInterest($interest)) {
                if (!$blockBased || !$user->existUnfollowInterest($interest)) {
                    $user->addFollowInterest($interest, $includeEntityRecursively);
                    $parent = $interest->getParent();
                    if ($parent and !$user->existFollowInterest($parent)) {
                        if($user->existUnfollowInterest($parent)) {
                            $this->removeUnfollowInterestUser($parent, $user,false);
                        }
                        $user->addFollowInterest($parent, false);
                    }
                    if ($user->existUnfollowInterest($interest)) {
                        $this->removeUnfollowInterestUser($interest, $user, $includeEntityRecursively);
                    }
                    $done = true;
                }
            }
        } else {
            if ($interest != null && !$user->existUnfollowInterest($interest)) {
                if (!$blockBased || !$user->existFollowInterest($interest)) {
                    $user->addUnfollowInterest($interest, $includeEntityRecursively);
                    if ($user->isLastChild($interest)) {
                        if($user->existFollowInterest($interest->getParent())) {
                            $this->removeFollowInterestUser($interest->getParent(), $user,false);
                        }
                        $user->addUnfollowInterest($interest->getParent(), false);
                    }
                    if ($user->existFollowInterest($interest)) {
                        $this->removeFollowInterestUser($interest, $user, $includeEntityRecursively);
                    }
                    $done = true;
                }
            }
        }

        $this->em->persist($user);
        if ($flush) {
            $this->em->flush();
        }

        return $done;
    }

    /**
     * Remove Interest from User
     *
     * @param Interest $interest
     * @param $user
     * @param string $followMode - by default: "followInterest"
     * @param bool $flush
     * @param bool $includeEntityRecursively
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function deleteInterestUser(Interest $interest, $user, $followMode = self::FOLLOW_INTEREST, $flush = false, $includeEntityRecursively = false) {
        /** @var UserTrait $user */
        $done = false;

        if (null === $user) {
            return $done;
        }

        if ($followMode == null || ($followMode != self::FOLLOW_INTEREST && $followMode != self::UNFOLLOW_INTEREST)) {
            $followMode = self::FOLLOW_INTEREST;
        }
        if ($followMode == self::FOLLOW_INTEREST) {
            if ($interest != null && $user->existFollowInterest($interest)) {
                $done = $this->removeFollowInterestUser($interest, $user, $includeEntityRecursively);
            }
        } else {
            if ($interest != null && $user->existUnfollowInterest($interest)) {
                $done = $this->removeUnfollowInterestUser($interest, $user,  $includeEntityRecursively);
            }
        }
        $this->em->persist($user);
        if ($flush) {
            $this->em->flush();
        }

        return $done;
    }

    /**
     * Return all Interests
     *
     * @return array
     */
    public function getInterests() {
        /** @var InterestRepository $repositoryInterest */
        $repositoryInterest = $this->em->getRepository("OpositatestInterestUserBundle:Interest");
        return $repositoryInterest->findAll();
    }

    /**
     * @param \Opositatest\InterestUserBundle\Entity\Interest $unfollowInterest
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     */
    public function removeUnfollowInterestUser( Interest $unfollowInterest, $user, $includeChildren = true) {
        /** @var UserTrait $user */

        /** @var UserInterestRepository $interestUserRepository */
        $interestUserRepository = $this->em->getRepository(UnFollowInterestUser::class);
        /** @var UnFollowInterestUser $unfollowInterestUser */
        $unfollowInterestUser = $interestUserRepository->findOneByUserAndInterest($unfollowInterest, $user);

        $user->removeUnfollowInterest($unfollowInterestUser);
        if ($includeChildren) {
            foreach($unfollowInterest->getChildren() as $child) {
                if ($user->existUnfollowInterest($child)) {
                    $this->removeUnfollowInterestUser($child, $user, $includeChildren);
                }
            }
        }
        $this->em->remove($unfollowInterestUser);
        return true;
    }

    /**
     * @param \Opositatest\InterestUserBundle\Entity\Interest $unfollowInterest
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     */
    public function removeFollowInterestUser( Interest $unfollowInterest, $user, $includeChildren = true) {
        /** @var UserTrait $user */

        /** @var UserInterestRepository $interestUserRepository */
        $interestUserRepository = $this->em->getRepository(FollowInterestUser::class);
        /** @var FollowInterestUser $followInterestUser */
        $followInterestUser = $interestUserRepository->findOneByUserAndInterest($unfollowInterest, $user);

        if($followInterestUser != null) {
            $user->removeFollowInterest($followInterestUser);

            if ($includeChildren) {
                foreach($unfollowInterest->getChildren() as $child) {
                    if ($user->existUnfollowInterest($child)) {
                        $this->removeFollowInterestUser($child, $user, $includeChildren);
                    }
                }
            }
            $this->em->remove($followInterestUser);
        }

        return true;

    }

}
