<?php

namespace Nsm\Bundle\ApiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;

/**
 * AbstractRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AbstractRepository extends EntityRepository
{
    /**
     * @param $criteria
     * @param null $alias
     * @return ProjectQueryBuilder
     */
    public function filter($criteria = null, $alias = null)
    {
        $qb = new ProjectQueryBuilder($this->getEntityManager(), $this, $alias = null);
        $qb->selectRootEntity();

        if(null !== $criteria) {
            $qb->filterByCriteria($criteria);
        }

        return $qb;
    }

    /**
     * @param array $criteria
     * @param bool $removeEmpty
     * @return $this
     */
    public function sanatiseCriteria(array $criteria, $removeEmpty = true)
    {
        $sanitisedCriteria = array();

        foreach ($criteria as $key => $value) {

            if ($value instanceof ArrayCollection) {
                $value = $this->transformCollectionToIdArray($value);
            } elseif ($value instanceof AbstractEntity) {
                $value = $value->getId();
            }

            // If not an empty value or a zero value
            if (false == empty($value) || 0 === $value || "0" === $value) {
                $sanitisedCriteria[$key] = $value;
            }
        }

        return $sanitisedCriteria;
    }
    
    /**
     * Take a collection and remap the collection to the id
     * 
     * @param $collection
     * @return array
     * @throws \Exception
     */
    public function transformCollectionToIdArray($collection)
    {
        if(!$collection instanceof \Iterator) {
            throw new \Exception('Collections is not instance of Iterator');
        }

        $ids = array();

        foreach($collection as $value) {
            $ids[] = is_array($value) ?  $value['id'] : $value->getId();
        }

        return array_unique($ids);
    }
    
}
