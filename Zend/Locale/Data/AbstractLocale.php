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
 * @package    Zend_Locale
 * @subpackage Cldr
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Locale\Data;

use Zend\Cache\Cache,
    Zend\Cache\Frontend as CacheFrontend,
    Zend\Locale\Locale,
    Zend\Locale\Exception\InvalidArgumentException;

/**
 * Locale data reader, handles the CLDR
 *
 * @uses       Zend\Cache\Cache
 * @uses       Zend\Locale
 * @uses       Zend\Locale\Exception\InvalidArgumentException
 * @category   Zend
 * @package    Zend_Locale
 * @subpackage Data
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class AbstractLocale
{
    /**
     * Locale files
     *
     * @var ressource
     * @access private
     */
    private static $_ldml = array();

    /**
     * List of values which are collected
     *
     * @var array
     * @access private
     */
    private static $_list = array();

    /**
     * Internal cache for ldml values
     *
     * @var \Zend\Cache\Core
     * @access private
     */
    protected static $_cache = null;

    /**
     * Internal option, cache disabled
     *
     * @var    boolean
     * @access private
     */
    protected static $_cacheDisabled = false;

    /**
     * Internal value to remember if cache supports tags
     *
     * @var boolean
     */
    protected static $_cacheTags = false;

    /**
     * Internal function for checking the locale
     *
     * @param string|\Zend\Locale $locale Locale to check
     * @return string
     */
    protected static function _checkLocale($locale)
    {
        if (empty($locale)) {
            $locale = new Locale();
        }

        if (!(Locale::isLocale((string) $locale))) {
            throw new InvalidArgumentException(
              "Locale (" . (string) $locale . ") is no known locale"
            );
        }

        return (string) $locale;
    }

    /**
     * Returns the set cache
     *
     * @return \Zend\Cache\Core The set cache
     */
    public static function getCache()
    {
        return self::$_cache;
    }

    /**
     * Set a cache for Zend_Locale_Data
     *
     * @param \Zend\Cache\Frontend $cache A cache frontend
     */
    public static function setCache(CacheFrontend $cache)
    {
        self::$_cache = $cache;
        self::_getTagSupportForCache();
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
     * @param string $tag Tag to clear when the default tag name is not used
     * @return void
     */
    public static function clearCache($tag = null)
    {
        if (self::$_cacheTags) {
            if ($tag == null) {
                $tag = 'Zend_Locale';
            }

            self::$_cache->clean(\Zend\Cache\Cache::CLEANING_MODE_MATCHING_TAG, array($tag));
        } else {
            self::$_cache->clean(\Zend\Cache\Cache::CLEANING_MODE_ALL);
        }
    }

    /**
     * Disables the cache
     *
     * @param unknown_type $flag
     */
    public static function disableCache($flag)
    {
        self::$_cacheDisabled = (boolean) $flag;
    }

    /**
     * Internal method to check if the given cache supports tags
     *
     * @return false|string
     */
    private static function _getTagSupportForCache()
    {
        $backend = self::$_cache->getBackend();
        if ($backend instanceof \Zend\Cache\Backend\ExtendedInterface) {
            $cacheOptions = $backend->getCapabilities();
            self::$_cacheTags = $cacheOptions['tags'];
        } else {
            self::$_cacheTags = false;
        }

        return self::$_cacheTags;
    }
}
