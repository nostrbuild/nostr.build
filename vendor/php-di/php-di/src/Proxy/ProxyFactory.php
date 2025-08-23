<?php

declare(strict_types=1);

namespace DI\Proxy;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;

/**
 * Creates proxy classes.
 *
 * Wraps Ocramius/ProxyManager LazyLoadingValueHolderFactory.
 *
 * @see LazyLoadingValueHolderFactory
 *
 * @since  5.0
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class ProxyFactory implements ProxyFactoryInterface
{
    private ?LazyLoadingValueHolderFactory $proxyManager = null;

    /**
     * @param string|null $proxyDirectory If set, write the proxies to disk in this directory to improve performances.
     */
    public function __construct(
        private ?string $proxyDirectory = null,
    ) {
    }

    /**
     * Creates a new lazy proxy instance of the given class with
     * the given initializer.
     *
     * {@inheritDoc}
     */
    public function createProxy(string $className, \Closure $createFunction): object
    {
        return $this->proxyManager()->createProxy(
            $className,
            function (& $wrappedObject, $proxy, $method, $params, & $initializer) use ($createFunction) {
                $wrappedObject = $createFunction();
                $initializer = null; // turning off further lazy initialization

                return true;
            }
        );
    }

    /**
     * Generates and writes the proxy class to file.
     *
     * @param class-string $className name of the class to be proxied
     */
    public function generateProxyClass(string $className) : void
    {
        // If proxy classes a written to file then we pre-generate the class
        // If they are not written to file then there is no point to do this
        if ($this->proxyDirectory) {
            $this->createProxy($className, function () {});
        }
    }

    private function proxyManager() : LazyLoadingValueHolderFactory
    {
        if ($this->proxyManager === null) {
            if (! class_exists(Configuration::class)) {
                throw new \RuntimeException('The ocramius/proxy-manager library is not installed. Lazy injection requires that library to be installed with Composer in order to work. Run "composer require ocramius/proxy-manager:~2.0".');
            }

            $config = new Configuration();

            if ($this->proxyDirectory) {
                $config->setProxiesTargetDir($this->proxyDirectory);
                $config->setGeneratorStrategy(new FileWriterGeneratorStrategy(new FileLocator($this->proxyDirectory)));
                // @phpstan-ignore-next-line
                spl_autoload_register($config->getProxyAutoloader());
            } else {
                $config->setGeneratorStrategy(new EvaluatingGeneratorStrategy());
            }

            $this->proxyManager = new LazyLoadingValueHolderFactory($config);
        }

        return $this->proxyManager;
    }
}
