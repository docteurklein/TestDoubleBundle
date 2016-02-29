<?php

namespace DocteurKlein;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Reference;

final class TestDoubleBundle extends Bundle implements CompilerPassInterface
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container)
    {
        $ids = [];
        foreach ($container->findTaggedServiceIds('test_double') as $id => $configs) {

            $definition = $container->getDefinition($id);
            $container->setDefinition("$id.real", $definition);

            foreach ($configs as $config) {
                if (!empty($config['fake'])) {
                    $container->setAlias($id, $config['fake']);
                }
                else {
                    $container->setDefinition("$id.prophecy", (new Definition)->setSynthetic(true));
                    $container->setDefinition("$id.stub", (new Definition)->setSynthetic(true));

                    $container->setAlias($id, "$id.stub");

                    $class = $definition->getClass();
                    if (isset($config['stub'])) {
                        $class = $config['stub'];
                    }
                    $ids[$id] = $class;
                }
            }
        }
        $container->setParameter('stub.services', $ids);
        $doubler = new Definition('Prophecy\Doubler\Doubler');
        $prophet = new Definition('Prophecy\Prophet', [$doubler]);
        foreach (['SplFileInfoPatch', 'TraversablePatch', 'DisableConstructorPatch', 'ProphecySubjectPatch', 'ReflectionClassNewInstancePatch', 'HhvmExceptionPatch', 'MagicCallPatch', 'KeywordPatch'] as $class) {
            $doubler->addMethodCall('registerClassPatch', [new Definition('Prophecy\Doubler\ClassPatch\\'.$class)]);
        }
        foreach ($container->findTaggedServiceIds('test_double.prophet.class_patch') as $id => $configs) {
            foreach ($configs as $config) {
                $doubler->addMethodCall('registerClassPatch', [new Reference($id)]);
            }
        }
        $container->setDefinition('stub.prophet', $prophet);
    }

    public function boot()
    {
        $prophet = $this->container->get('stub.prophet');
        foreach ($this->container->getParameter('stub.services') as $id => $class) {
            $prophecy = $prophet->prophesize($class);
            $this->container->set("$id.prophecy", $prophecy);
            $this->container->set("$id.stub", $prophecy->reveal());
        }
    }
}
