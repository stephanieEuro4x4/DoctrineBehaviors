<?php

declare(strict_types=1);

namespace Knp\DoctrineBehaviors\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Knp\DoctrineBehaviors\Contract\Entity\TreeNodeInterface;

/**
 * Class TreeEventSubscriber.
 *
 * */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final class TreeEventSubscriber
{
    /**
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();
        if (null === $classMetadata->reflClass) {
            // Class has not yet been fully built, ignore this event
            return;
        }

        if (!is_a($classMetadata->reflClass->getName(), TreeNodeInterface::class, true)) {
            return;
        }

        if ($classMetadata->hasField('materializedPath')) {
            return;
        }

        $classMetadata->mapField([
            'fieldName' => 'materializedPath',
            'type' => 'string',
            'length' => 255,
        ]);
    }
}
