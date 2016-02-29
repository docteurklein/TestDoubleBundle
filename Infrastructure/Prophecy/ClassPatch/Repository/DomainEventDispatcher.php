<?php

namespace App\Infrastructure\Prophecy\ClassPatch\Repository;

use Prophecy\Doubler\Generator\Node\ClassNode;
use JMS\DiExtraBundle\Annotation as DI;
use Prophecy\Doubler\ClassPatch\ClassPatchInterface;

/**
 * @DI\Service
 * @DI\Tag("test_double.prophet.class_patch")
 */
final class DomainEventDispatcher implements ClassPatchInterface
{
    public function supports(ClassNode $node)
    {
        return $node->hasInterface('Qspot\Domain\Repository\SeasonTickets');
    }

    public function apply(ClassNode $node)
    {
        $node->getMethod('save')->setCode('die("here");');
    }

    public function getPriority()
    {
        return -50;
    }
}
