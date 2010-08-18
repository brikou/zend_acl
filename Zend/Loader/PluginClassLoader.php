<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Loader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Loader;

/**
 * Plugin class locater interface
 *
 * @category   Zend
 * @package    Zend_Loader
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class PluginClassLoader implements PluginClassLocater
{
    /**
     * List of plugin name => class name pairs
     * @var array
     */
    protected $plugins = array();

    /**
     * Constructor
     * 
     * @param  null|array|Traversable $map If provided, seeds the loader with a map
     * @return void
     */
    public function __construct($map = null)
    {
        if ($map !== null) {
            $this->registerPlugins($map);
        }
    }

    /**
     * Register a class to a given short name
     * 
     * @param  string $shortName 
     * @param  string $className 
     * @return PluginClassLoader
     */
    public function registerPlugin($shortName, $className)
    {
        $this->plugins[strtolower($shortName)] = $className;
        return $this;
    }

    /**
     * Register many plugins at once
     * 
     * @param  array|Traversable $map 
     * @return PluginClassLoader
     */
    public function registerPlugins($map)
    {
        if (!is_array($map) && !$map instanceof Traversable) {
            throw new InvalidArgumentException('Map provided is invalid; must be an array or traversable');
        }

        foreach ($map as $plugin => $class)  {
            $this->registerPlugin($plugin, $class);
        }
        return $this;
    }

    /**
     * Unregister a short name lookup
     * 
     * @param mixed $shortName 
     * @return PluginClassLoader
     */
    public function unregisterPlugin($shortName)
    {
        $lookup = strtolower($shortName);
        if (array_key_exists($lookup, $this->plugins)) {
            unset($this->plugins[$lookup]);
        }
        return $this;
    }

    /**
     * Get a list of all registered plugins
     * 
     * @return array|Traversable
     */
    public function getRegisteredPlugins()
    {
        return $this->plugins;
    }

    /**
     * Whether or not a Helper by a specific name
     *
     * @param  string $name
     * @return bool
     */
    public function isLoaded($name)
    {
        $lookup = strtolower($name);
        return isset($this->plugins[$lookup]);
    }

    /**
     * Return full class name for a named helper
     *
     * @param  string $name
     * @return string|false
     */
    public function getClassName($name)
    {
        return $this->load($name);
    }

    /**
     * Load a helper via the name provided
     *
     * @param  string $name
     * @return string|false
     */
    public function load($name)
    {
        if (!$this->isLoaded($name)) {
            return false;
        }
        return $this->plugins[strtolower($name)];
    }
}
