<?php

declare(strict_types=1);

namespace Knp\DoctrineBehaviors\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\UnitOfWork;
use Knp\DoctrineBehaviors\Contract\Entity\BlameableInterface;
use Knp\DoctrineBehaviors\Contract\Provider\UserProviderInterface;

/**
 * Class BlameableEventSubscriber.
 *
 * */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class BlameableEventSubscriber
{
    /**
     * @var string
     */
    private const DELETED_BY = 'deletedBy';

    /**
     * @var string
     */
    private const UPDATED_BY = 'updatedBy';

    /**
     * @var string
     */
    private const CREATED_BY = 'createdBy';

    /**
     * @param UserProviderInterface  $userProvider
     * @param EntityManagerInterface $entityManager
     * @param string|null            $blameableUserEntity
     */
    public function __construct(
        private UserProviderInterface $userProvider,
        private EntityManagerInterface $entityManager,
        private ?string $blameableUserEntity = null,
    ) {
    }

    /**
     * Adds metadata about how to store user, either a string or an ManyToOne association on user entity.
     *
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();
        if (null === $classMetadata->reflClass) {
            // Class has not yet been fully built, ignore this event
            return;
        }

        if (!is_a($classMetadata->reflClass->getName(), BlameableInterface::class, true)) {
            return;
        }

        $this->mapEntity($classMetadata);
    }

    /**
     * Stores the current user into createdBy and updatedBy properties.
     */
    public function prePersist(PrePersistEventArgs $prePersistEventArgs): void
    {
        $entity = $prePersistEventArgs->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $user = $this->userProvider->provideUser();
        // no user set → skip
        if (null === $user) {
            return;
        }

        if (!$entity->getCreatedBy()) {
            $entity->setCreatedBy($user);

            $this->getUnitOfWork()
                ->propertyChanged($entity, self::CREATED_BY, null, $user);
        }

        if (!$entity->getUpdatedBy()) {
            $entity->setUpdatedBy($user);

            $this->getUnitOfWork()
                ->propertyChanged($entity, self::UPDATED_BY, null, $user);
        }
    }

    /**
     * Stores the current user into updatedBy property.
     */
    public function preUpdate(PreUpdateEventArgs $preUpdateEventArgs): void
    {
        $entity = $preUpdateEventArgs->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $user = $this->userProvider->provideUser();
        if (null === $user) {
            return;
        }

        $oldValue = $entity->getUpdatedBy();
        $entity->setUpdatedBy($user);

        $this->getUnitOfWork()
            ->propertyChanged($entity, self::UPDATED_BY, $oldValue, $user);
    }

    /**
     * Stores the current user into deletedBy property.
     */
    public function preRemove(PreRemoveEventArgs $preRemoveEventArgs): void
    {
        $entity = $preRemoveEventArgs->getObject();
        if (!$entity instanceof BlameableInterface) {
            return;
        }

        $user = $this->userProvider->provideUser();
        if (null === $user) {
            return;
        }

        $oldDeletedBy = $entity->getDeletedBy();
        $entity->setDeletedBy($user);

        $this->getUnitOfWork()
            ->propertyChanged($entity, self::DELETED_BY, $oldDeletedBy, $user);
    }

    /**
     * @throws MappingException
     */
    private function mapEntity(ClassMetadataInfo $classMetadataInfo): void
    {
        if (null !== $this->blameableUserEntity && class_exists($this->blameableUserEntity)) {
            $this->mapManyToOneUser($classMetadataInfo);
        } else {
            $this->mapStringUser($classMetadataInfo);
        }
    }

    /**
     * @return UnitOfWork
     */
    private function getUnitOfWork(): UnitOfWork
    {
        return $this->entityManager->getUnitOfWork();
    }

    /**
     * @param ClassMetadataInfo $classMetadataInfo
     *
     * @return void
     */
    private function mapManyToOneUser(ClassMetadataInfo $classMetadataInfo): void
    {
        $this->mapManyToOneWithTargetEntity($classMetadataInfo, self::CREATED_BY);
        $this->mapManyToOneWithTargetEntity($classMetadataInfo, self::UPDATED_BY);
        $this->mapManyToOneWithTargetEntity($classMetadataInfo, self::DELETED_BY);
    }

    /**
     * @throws MappingException
     */
    private function mapStringUser(ClassMetadataInfo $classMetadataInfo): void
    {
        $this->mapStringNullableField($classMetadataInfo, self::CREATED_BY);
        $this->mapStringNullableField($classMetadataInfo, self::UPDATED_BY);
        $this->mapStringNullableField($classMetadataInfo, self::DELETED_BY);
    }

    /**
     * @param ClassMetadataInfo $classMetadataInfo
     * @param string            $fieldName
     *
     * @return void
     */
    private function mapManyToOneWithTargetEntity(ClassMetadataInfo $classMetadataInfo, string $fieldName): void
    {
        if ($classMetadataInfo->hasAssociation($fieldName)) {
            return;
        }

        $classMetadataInfo->mapManyToOne([
            'fieldName' => $fieldName,
            'targetEntity' => $this->blameableUserEntity,
            'joinColumns' => [
                [
                    'onDelete' => 'SET NULL',
                ],
            ],
        ]);
    }

    /**
     * @throws MappingException
     */
    private function mapStringNullableField(ClassMetadataInfo $classMetadataInfo, string $fieldName): void
    {
        if ($classMetadataInfo->hasField($fieldName)) {
            return;
        }

        $classMetadataInfo->mapField([
            'fieldName' => $fieldName,
            'type' => 'string',
            'nullable' => true,
        ]);
    }
}
