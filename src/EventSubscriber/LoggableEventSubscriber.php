<?php

declare(strict_types=1);

namespace Knp\DoctrineBehaviors\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Knp\DoctrineBehaviors\Contract\Entity\LoggableInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggableEventSubscriber
 *
 * */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class LoggableEventSubscriber
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param PostPersistEventArgs $postPersistEventArgs
     *
     * @return void
     */
    public function postPersist(PostPersistEventArgs $postPersistEventArgs): void
    {
        $entity = $postPersistEventArgs->getObject();
        if (!$entity instanceof LoggableInterface) {
            return;
        }

        $createLogMessage = $entity->getCreateLogMessage();
        $this->logger->log(LogLevel::INFO, $createLogMessage);

        $this->logChangeSet($postPersistEventArgs);
    }

    /**
     * @param PostUpdateEventArgs $postUpdateEventArgs
     *
     * @return void
     */
    public function postUpdate(PostUpdateEventArgs $postUpdateEventArgs): void
    {
        $entity = $postUpdateEventArgs->getObject();
        if (!$entity instanceof LoggableInterface) {
            return;
        }

        $this->logChangeSet($postUpdateEventArgs);
    }

    /**
     * @param PreRemoveEventArgs $preRemoveEventArgs
     *
     * @return void
     */
    public function preRemove(PreRemoveEventArgs $preRemoveEventArgs): void
    {
        $entity = $preRemoveEventArgs->getObject();

        if ($entity instanceof LoggableInterface) {
            $this->logger->log(LogLevel::INFO, $entity->getRemoveLogMessage());
        }
    }

    /**
     * Logs entity changeset.
     */
    private function logChangeSet(LifecycleEventArgs $lifecycleEventArgs): void
    {
        $entityManager = $lifecycleEventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $entity = $lifecycleEventArgs->getObject();

        $entityClass = $entity::class;
        $classMetadata = $entityManager->getClassMetadata($entityClass);

        /* @var LoggableInterface $entity */
        $unitOfWork->computeChangeSet($classMetadata, $entity);
        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        $message = $entity->getUpdateLogMessage($changeSet);

        if ('' === $message) {
            return;
        }

        $this->logger->log(LogLevel::INFO, $message);
    }
}
