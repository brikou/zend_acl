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
 * @category  Zend
 * @package   Zend_Uri
 * @copyright Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Uri;

/**
 * HTTP URI handler
 *
 * @category  Zend
 * @package   Zend_Uri
 * @copyright Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */
class Http extends Uri
{
    /**
     * @see Uri::$validSchemes
     */
    static protected $validSchemes = array('http', 'https');
    
    /**
     * @see Uri::$defaultPorts
     */
    static protected $defaultPorts = array(
        'http'  => 80,
        'https' => 443,
    );
    
    /**
     * @see Uri::$validHostTypes
     */
    protected $validHostTypes = self::HOST_DNSORIPV4;
    
    /**
     * Check if the URI is a valid HTTP URI
     * 
     * This applys additional HTTP specific validation rules beyond the ones 
     * required by the generic URI syntax
     * 
     * @return boolean
     * @see    Uri::isValid()
     */
    public function isValid()
    {
        return parent::isValid();
    }

    /**
     * Get the username part (before the ':') of the userInfo URI part
     * 
     * @return string
     */
    public function getUser()
    {
    }
    
    /**
     * Get the password part (after the ':') of the userInfo URI part
     * 
     * @return string
     */
    public function getPassword()
    {
    }

    /**
     * Set the username part (before the ':') of the userInfo URI part
     * 
     * @param  string $user
     * @return Http
     */
    public function setUser($user)
    {
        return $this;
    }
    
    /**
     * Set the password part (after the ':') of the userInfo URI part
     * 
     * @param  string $password
     * @return Http
     */
    public function setPassword($password)
    {
        return $this;
    }
    
    /**
     * Validate the host part of an HTTP URI
     * 
     * This overrides the common URI validation method with a DNS or IPv4 only
     * default. Users may still enforce allowing other host types.
     * 
     * @param  string  $host
     * @param  integer $allowed
     * @return boolean
     */
    static public function validateHost($host, $allowed = self::HOST_DNSORIPV4)
    {
        return parent::validateHost($host, $allowed);
    }
}
