<?php

namespace Zend\Cache;
use Zend\Cache\Storage,
    Zend\Cache\Exception\InvalidArgumentException,
    Zend\Loader\Broker,
    Zend\Config;

class StorageFactory
{
    /**
     * Broker for loading adapters
     *
     * @var null|Zend\Loader\Broker
     */
    protected static $_adapterBroker = null;

    /**
     * Broker for loading plugins
     *
     * @var null|Zend\Loader\Broker
     */
    protected static $_pluginBroker = null;

    /**
     * The storage factory
     * This can instantiate storage adapters and plugins.
     *
     * @param array|\Zend\Config $cfg
     */
    public static function factory($cfg)
    {
        if ($cfg instanceof Config\Config) {
            $cfg = $cfg->toArray();
        } elseif (!is_array($cfg)) {
            throw new InvalidArgumentException(
                'The factory needs an instance of \Zend\Config\Config '
              . 'or an associative array as argument'
            );
        }

        // instantiate the adapter
        if (!isset($cfg['adapter'])) {
            throw new InvalidArgumentException(
                'Missing "adapter"'
            );
        } elseif (is_array($cfg['adapter'])) {
            if (!isset($cfg['adapter']['name'])) {
                throw new InvalidArgumentException(
                    'Missing "adapter.name"'
                );
            }

            $name    = $cfg['adapter']['name'];
            $options = isset($cfg['adapter']['options'])
                     ? $cfg['adapter']['options'] : array();
            $adapter = self::adapterFactory($name, $options);
        } else {
            $adapter = self::adapterFactory($cfg['adapter']);
        }

        // add plugins
        if (isset($cfg['plugins'])) {
            if (!is_array($cfg['plugins'])) {
                throw new InvalidArgumentException(
                    'Plugins needs to be an array'
                );
            }

            $plugins = array_reverse($cfg['plugins']);
            foreach ($plugins as $k => $v) {
                if (is_string($k)) {
                    $name = $k;
                    if (!is_array($v)) {
                        throw new InvalidArgumentException(
                            "'plugins.{$k}' needs to be an array"
                        );
                    }
                    $options = $v;
                    $options['storage'] = $adapter;
                } elseif (is_array($v)) {
                    if (!isset($v['name'])) {
                        throw new InvalidArgumentException("Invalid plugins[{$k}] or missing plugins[{$k}].name");
                    }
                    $name = (string)$v['name'];
                    if (isset($v['options'])) {
                        $options = $v['options'];
                        $options['storage'] = $adapter;
                    } else {
                        $options = array('storage' => $adapter);
                    }
                } else {
                    $name    = $v;
                    $options = array('storage' => $adapter);
                }

                $adapter = self::pluginFactory($name, $options);
            }
        }

        // set adapter or plugin options
        if (isset($cfg['options'])) {
            if (!is_array($cfg['options'])) {
                throw new InvalidArgumentException(
                    'Options needs to be an array'
                );
            }

            $adapter->setOptions($cfg['options']);
        }

        return $adapter;
    }

    /**
     * Instantiate a storage adapter
     *
     * @param string|Zend\Cache\Storage\Adapter $adapterName
     * @param array|Zend\Config $options
     * @return Zend\Cache\Storage\Adapter
     * @throws Zend\Cache\RuntimeException
     */
    public static function adapterFactory($adapterName, $options = array())
    {
        if ($adapterName instanceof Storage\Adapter) {
            // $adapterName is already an adapter object
            $adapterName->setOptions($options);
            return $adapterName;
        }

        return self::getAdapterBroker()->load($adapterName, $options);
    }

    /**
     * Get the adapter broker
     *
     * @return Zend\Loader\Broker
     */
    public static function getAdapterBroker()
    {
        if (self::$_adapterBroker === null) {
            self::$_adapterBroker = new Storage\AdapterBroker();
        }
        return self::$_adapterBroker;
    }

    /**
     * Change the adapter broker
     *
     * @param  Zend\Loader\Broker $broker
     * @return void
     */
    public static function setAdapterBroker(Broker $broker)
    {
        self::$_adapterBroker = $broker;
    }

    /**
     * Resets the internal adapter broker
     */
    public static function resetAdapterBroker()
    {
        self::$_adapterBroker = new Storage\AdapterBroker();
    }

    /**
     * Instantiate a storage plugin
     *
     * @param string|Zend\Cache\Storage\Plugin $pluginName
     * @param array|Zend\Config $options
     * @return Zend\Cache\Storage\Plugin
     * @throws Zend\Cache\RuntimeException
     */
    public static function pluginFactory($pluginName, $options = array())
    {
        if ($pluginName instanceof Storage\Plugin) {
            // $pluginName is already an plugin object
            $pluginName->setOptions($options);
            return $pluginName;
        }

        return self::getPluginBroker()->load($pluginName, $options);
    }

    /**
     * Get the plugin broker
     *
     * @return Zend\Loader\Broker
     */
    public static function getPluginBroker()
    {
        if (self::$_pluginBroker === null) {
            self::$_pluginBroker = new Storage\PluginBroker();
        }
        return self::$_pluginBroker;
    }

    /**
     * Change the plugin broker
     *
     * @param  Zend\Loader\Broker $broker
     * @return void
     */
    public static function setPluginBroker(Broker $broker)
    {
        self::$_pluginBroker = $broker;
    }

    /**
     * Resets the internal plugin broker
     */
    public static function resetPluginBroker()
    {
        self::$_pluginBroker = new Storage\PluginBroker();
    }

}
