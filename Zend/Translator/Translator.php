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
 * @package    Zend_Translate
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Translator;

/**
 * @uses       \Zend\Loader
 * @uses       \Zend\Translator\Exception
 * @category   Zend
 * @package    Zend_Translate
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Translator
{

    /**
     * Adapter names constants
     */
    const AN_ARRAY   = 'ArrayAdapter';
    const AN_CSV     = 'Csv';
    const AN_GETTEXT = 'Gettext';
    const AN_INI     = 'Ini';
    const AN_QT      = 'Qt';
    const AN_TBX     = 'Tbx';
    const AN_TMX     = 'Tmx';
    const AN_XLIFF   = 'Xliff';
    const AN_XMLTM   = 'XmlTm';

    const LOCALE_DIRECTORY = 'directory';
    const LOCALE_FILENAME  = 'filename';

    /**
     * Adapter
     *
     * @var \Zend\Translator\Adapter
     */
    private $_adapter;
    private static $_cache = null;

    /**
     * Generates the standard translation object
     *
     * @param  array|Zend_Config $options Options to use
     * @throws Zend_Translate_Exception
     */
    public function __construct($options = array())
    {
        if ($options instanceof \Zend\Config\Config) {
            $options = $options->toArray();
        } else if (func_num_args() > 1) {
            $args               = func_get_args();
            $options            = array();
            $options['adapter'] = array_shift($args);
            if (!empty($args)) {
                $options['content'] = array_shift($args);
            }

            if (!empty($args)) {
                $options['locale'] = array_shift($args);
            }

            if (!empty($args)) {
                $opt     = array_shift($args);
                $options = array_merge($opt, $options);
            }
        } else if (!is_array($options)) {
            $options = array('adapter' => $options);
        }

        $this->setAdapter($options);
    }

    /**
     * Sets a new adapter
     *
     * @param  array|Zend_Config $options Options to use
     * @throws Zend_Translate_Exception
     */
    public function setAdapter($options = array())
    {
        if ($options instanceof \Zend\Config\Config) {
            $options = $options->toArray();
        } elseif (func_num_args() > 1) {
            $args               = func_get_args();
            $options            = array();
            $options['adapter'] = array_shift($args);
            if (!empty($args)) {
                $options['content'] = array_shift($args);
            }

            if (!empty($args)) {
                $options['locale'] = array_shift($args);
            }

            if (!empty($args)) {
                $opt     = array_shift($args);
                $options = array_merge($opt, $options);
            }
        } else if (!is_array($options)) {
            $options = array('adapter' => $options);
        }

        if (\Zend\Loader::isReadable('Zend/Translator/Adapter/' . ucfirst($options['adapter']). '.php')) {
            $options['adapter'] = 'Zend\Translator\Adapter\\' . ucfirst($options['adapter']);
        }

        if (!class_exists($options['adapter'])) {
            throw new Exception("Adapter " . $options['adapter'] . " does not exist and cannot be loaded");
        }

        if (array_key_exists('cache', $options)) {
            self::setCache($options['cache']);
        }

        if (self::$_cache !== null) {
            $options['cache'] = self::getCache();
        }

        $adapter = $options['adapter'];
        unset($options['adapter']);
        $this->_adapter = new $adapter($options);
        if (!$this->_adapter instanceof Adapter) {
            throw new Exception("Adapter " . $adapter . " does not extend Zend\Translate\Adapter");
        }
    }

    /**
     * Returns the adapters name and it's options
     *
     * @return \Zend\Translator\Adapter
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Returns the set cache
     *
     * @return \Zend\Cache\Frontend\Core The set cache
     */
    public static function getCache()
    {
        return self::$_cache;
    }

    /**
     * Sets a cache for all instances of Zend_Translate
     *
     * @param  \Zend\Cache\Frontend\Core $cache Cache to store to
     * @return void
     */
    public static function setCache(\Zend\Cache\Frontend\Core $cache)
    {
        self::$_cache = $cache;
    }

    /**
     * Returns true when a cache is set
     *
     * @return boolean
     */
    public static function hasCache()
    {
        if (self::$_cache !== null) {
            return true;
        }

        return false;
    }

    /**
     * Removes any set cache
     *
     * @return void
     */
    public static function removeCache()
    {
        self::$_cache = null;
    }

    /**
     * Clears all set cache data
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$_cache->clean();
    }

    /**
     * Calls all methods from the adapter
     */
    public function __call($method, array $options)
    {
        if (method_exists($this->_adapter, $method)) {
            return call_user_func_array(array($this->_adapter, $method), $options);
        }
        throw new Exception("Unknown method '" . $method . "' called!");
    }
}
