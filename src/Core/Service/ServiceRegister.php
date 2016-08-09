<?php

namespace Kraken\Core\Service;

use Kraken\Core\CoreInterface;
use Kraken\Throwable\Exception\Runtime\ExecutionException;
use Kraken\Throwable\Exception\Logic\IllegalCallException;
use Kraken\Throwable\Exception\Logic\InvalidArgumentException;
use Kraken\Throwable\Exception\Logic\Resource\ResourceDefinedException;
use Kraken\Throwable\Exception\Logic\Resource\ResourceUndefinedException;
use Kraken\Throwable\Exception\Runtime\OverflowException;
use Error;
use Exception;

class ServiceRegister implements ServiceRegisterInterface
{
    /**
     * @var CoreInterface
     */
    protected $core;

    /**
     * @var ServiceProviderInterface[]
     */
    protected $serviceProviders;

    /**
     * @var string[]
     */
    protected $serviceAliases;

    /**
     * @var bool
     */
    protected $booted;

    /**
     * @param CoreInterface $core
     */
    public function __construct(CoreInterface $core)
    {
        $this->core = $core;
        $this->serviceProviders = [];
        $this->serviceAliases = [];
        $this->booted = false;
    }

    /**
     *
     */
    public function __destruct()
    {
        foreach ($this->serviceProviders as $provider)
        {
            try
            {
                $this->unregisterProvider($provider);
            }
            catch (Error $ex)
            {}
            catch (Exception $ex)
            {}
        }

        unset($this->core);
        unset($this->serviceProviders);
        unset($this->serviceAliases);
        unset($this->booted);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function boot()
    {
        try
        {
            $this->sortProviders();
            $this->registerProviders();
            $this->bootProviders();
            $this->registerAliases();

            $this->booted = true;
            return;
        }
        catch (Error $ex)
        {}
        catch (Exception $ex)
        {}

        throw new ExecutionException("ServiceRegister could not be booted.", $ex);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function registerProvider($provider)
    {
        if ($this->getProvider($provider) !== null)
        {
            throw new ResourceDefinedException("ServiceProvider " . $this->getProviderClass($provider) . " already registered.");
        }

        if (is_string($provider) && ($class = $provider) && ($provider = $this->resolveProviderClass($provider)) === null)
        {
            throw new InvalidArgumentException("ServiceProvider $class is not valid provider.");
        }

        $ex = null;

        try
        {
            if ($this->booted)
            {
                $provider->registerProvider($this->core);
            }
        }
        catch (Error $ex)
        {}
        catch (Exception $ex)
        {}

        if ($ex !== null)
        {
            throw new ExecutionException("ServiceProvider " . $this->getProviderClass($provider) . " failed during registration.", $ex);
        }

        $this->markProviderRegistered($provider);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function unregisterProvider($provider)
    {
        if (is_string($provider) && ($class = $provider) && ($provider = $this->resolveProviderClass($provider)) === null)
        {
            throw new InvalidArgumentException("ServiceProvider $class is not valid provider.");
        }

        if (($provider = $this->getProvider($provider)) === null)
        {
            throw new ResourceUndefinedException("ServiceProvider " . $this->getProviderClass($provider) . " not registered.");
        }

        $provider->unregisterProvider($this->core);

        $this->markProviderUnregistered($provider);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getProvider($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        foreach ($this->serviceProviders as $key=>$provider)
        {
            if ($provider instanceof $name)
            {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getProviders()
    {
        $names = [];
        foreach ($this->serviceProviders as $provider)
        {
            $names[] = get_class($provider);
        }

        return $names;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getServices()
    {
        $services = [];
        foreach ($this->serviceProviders as $provider)
        {
            $services = array_merge($services, $provider->provides());
        }

        return $services;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function flushProviders()
    {
        if ($this->booted)
        {
            throw new IllegalCallException("Method ServiceRegister::flushProviders() cannot be called after boot up.");
        }

        $this->serviceProviders = [];
    }

    /**
     * @override
     * @inheritDoc
     */
    public function registerAlias($alias, $existing)
    {
        if ($this->getAlias($alias) !== null)
        {
            throw new ResourceDefinedException("ServiceProvider alias of $alias is already registered.");
        }

        $this->serviceAliases[$alias] = $existing;

        if ($this->booted)
        {
            $ex = null;

            try
            {
                $this->core->alias($alias, $existing);
            }
            catch (Error $ex)
            {}
            catch (Exception $ex)
            {}

            if ($ex !== null)
            {
                throw new ExecutionException("Alias [$alias] could not be registered.", $ex);
            }
        }
    }

    /**
     * @override
     * @inheritDoc
     */
    public function unregisterAlias($alias)
    {
        if ($this->getAlias($alias) === null)
        {
            throw new ResourceUndefinedException("ServiceProvider alias for $alias is not registered.");
        }

        $this->core->remove($alias);

        unset($this->serviceAliases[$alias]);
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getAlias($alias)
    {
        return isset($this->serviceAliases[$alias]) ? $this->serviceAliases[$alias] : null;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getAliases()
    {
        return $this->serviceAliases;
    }

    /**
     * @override
     * @inheritDoc
     */
    public function flushAliases()
    {
        if ($this->booted)
        {
            throw new IllegalCallException("\$this->flushAliases() cannot be called after boot up.");
        }

        $this->serviceAliases = [];
    }

    /**
     * @param ServiceProviderInterface $provider
     */
    private function markProviderRegistered(ServiceProviderInterface $provider)
    {
        $this->serviceProviders[] = $provider;
    }

    /**
     * @param ServiceProviderInterface $provider
     */
    private function markProviderUnregistered(ServiceProviderInterface $provider)
    {
        foreach ($this->serviceProviders as $index=>$currentProvider)
        {
            if ($provider instanceof $currentProvider)
            {
                unset($this->serviceProviders[$index]);
                break;
            }
        }
    }

    /**
     * @throws OverflowException
     */
    private function sortProviders()
    {
        $this->serviceProviders = (new ServiceSorter)->sortProviders($this->serviceProviders);
    }

    /**
     * @throws ExecutionException
     */
    private function registerProviders()
    {
        foreach ($this->serviceProviders as $provider)
        {
            $ex = null;

            try
            {
                if (!$provider->isRegistered())
                {
                    $provider->registerProvider($this->core);
                }
            }
            catch (Error $ex)
            {}
            catch (Exception $ex)
            {}

            if ($ex !== null)
            {
                throw new ExecutionException("ServiceProvider " . $this->getProviderClass($provider) . " failed during registration.", $ex);
            }
        }
    }

    /**
     * @throws ExecutionException
     */
    private function registerAliases()
    {
        foreach ($this->serviceAliases as $alias=>$concrete)
        {
            $ex = null;

            try
            {
                $this->core->alias($alias, $concrete);
            }
            catch (Error $ex)
            {}
            catch (Exception $ex)
            {}

            if ($ex !== null)
            {
                throw new ExecutionException("Alias [$alias] could not have be registered.", $ex);
            }
        }
    }

    /**
     * @throws ExecutionException
     */
    private function bootProviders()
    {
        foreach ($this->serviceProviders as $provider)
        {
            $ex = null;

            try
            {
                $provider->bootProvider($this->core);
            }
            catch (Error $ex)
            {}
            catch (Exception $ex)
            {}

            if ($ex !== null)
            {
                throw new ExecutionException("ServiceProvider " . $this->getProviderClass($provider) . " failed during boot.", $ex);
            }
        }
    }

    /**
     * @param string $providerClass
     * @return ServiceProviderInterface|null
     */
    private function resolveProviderClass($providerClass)
    {
        if (!class_exists($providerClass) || ($provider = new $providerClass($this)) instanceof ServiceProviderInterface === false)
        {
            return null;
        }

        return $provider;
    }

    /**
     * @param ServiceProviderInterface|string $provider
     * @return string
     */
    private function getProviderClass($provider)
    {
        return is_string($provider) ? $provider : get_class($provider);
    }
}
