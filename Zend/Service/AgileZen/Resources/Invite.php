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
 * @package    Zend\Service\AgileZen
 * @subpackage Resources
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
namespace Zend\Service\AgileZen\Resources;

use Zend\Service\AgileZen\AgileZen,
    Zend\Service\AgileZen\Entity;

/**
 * @category   Zend
 * @package    Zend\Service\AgileZen
 * @subpackage Resources
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Invite extends Entity
{
    /**
     * Create time
     * 
     * @var string
     */
    protected $createTime;
    /**
     * Service
     * 
     * @var Zend\Service\AgileZen\AgileZen 
     */
    protected $service;
    /**
     * Email
     * 
     * @var string 
     */
    protected $email;
    /**
     * Token
     * 
     * @var string
     */
    protected $token;
    /**
     * Sender
     * 
     * @var User 
     */
    protected $sender;
    /**
     * Role
     * 
     * @var Role 
     */
    protected $role;
    /**
     * Constructor
     * 
     * @param AgileZen $service
     * @param array $data 
     */
    public function __construct(AgileZen $service, $data)
    {
        if (!($service instanceof AgileZen) || !is_array($data)) {
             throw new Exception\InvalidArgumentException("You must pass a AgileZen object and an array");
        }
        if (!array_key_exists('id', $data)) {
             throw new Exception\InvalidArgumentException("You must pass the id of the invite");
        }
        $this->createTime = $data['createTime'];
        $this->email = $data['email'];
        $this->token = $data['token'];
        $this->sender = new User($service, $data['sender']);
        $this->role = new Role($service, $data['role']);  
        $this->service= $service;
        
        parent::__construct($data['id']);
    }
    /**
     * Get the create time
     * 
     * @return string 
     */
    public function getCreateTime()
    {
        return $this->createTime;
    }
    /**
     * Get the email
     * 
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }
    /**
     * Get the token
     * 
     * @return string 
     */
    public function getToken()
    {
        return $this->token;
    }
    /**
     * Get the sender
     * 
     * @return User 
     */
    public function getSender()
    {
        return $this->sender;
    }
    /**
     * Get the role
     * 
     * @return Role 
     */
    public function getRole()
    {
        return $this->role;
    }
}