<?php

namespace Zend\Mail;

use Countable,
    Iterator;

class AddressList implements Countable, Iterator
{
    /**
     * List of Address objects we're managing
     * 
     * @var array
     */
    protected $addresses = array();

    /**
     * Add an address to the list
     * 
     * @param  string|AddressDescription $emailOrAddress 
     * @param  null|string $name 
     * @return AddressList
     */
    public function add($emailOrAddress, $name = null)
    {
        if (is_string($emailOrAddress)) {
            $emailOrAddress = $this->createAddress($emailOrAddress, $name);
        }
        if (!$emailOrAddress instanceof AddressDescription) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an email address or %s\Address object as its first argument; received "%s"',
                __METHOD__,
                __NAMESPACE__,
                (is_object($emailOrAddress) ? get_class($emailOrAddress) : gettype($emailOrAddress))
            ));
        }

        $email = strtolower($emailOrAddress->getEmail());
        if (isset($this->addresses[$email])) {
            // Already have this one
            return $this;
        }

        $this->addresses[$email] = $emailOrAddress;
        return $this;
    }

    /**
     * Add many addresses at once
     *
     * If an email key is provided, it will be used as the email, and the value 
     * as the name. Otherwise, the value is passed as the sole argument to add(), 
     * and, as such, can be either email strings or AddressDescription objects.
     * 
     * @param  array $addresses 
     * @return AddressList
     */
    public function addMany(array $addresses)
    {
        foreach ($addresses as $key => $value) {
            if (is_int($key) || is_numeric($key)) {
                $this->add($value);
            } elseif (is_string($key)) {
                $this->add($value, $key);
            } else {
                throw new Exception\RuntimeException(sprintf(
                    'Invalid key type in provided addresses array ("%s")',
                    (is_object($key) ? get_class($key) : var_export($key, 1))
                ));
            }
        }
        return $this;
    }

    /**
     * Merge another address list into this one 
     * 
     * @param  AddressList $addressList 
     * @return AddressList
     */
    public function merge(AddressList $addressList)
    {
        foreach ($addressList as $address) {
            $this->add($address);
        }
        return $this;
    }

    /**
     * Does the email exist in this list?
     * 
     * @param  string $email 
     * @return bool
     */
    public function has($email)
    {
        $email = strtolower($email);
        return isset($this->addresses[$email]);
    }

    public function get($email)
    {
        $email = strtolower($email);
        if (!isset($this->addresses[$email])) {
            return false;
        }

        return $this->addresses[$email];
    }

    public function delete($emailOrAddress)
    {
        $email = strtolower($email);
        if (!isset($this->addresses[$email])) {
            return false;
        }

        unset($this->addresses[$email]);
        return true;
    }

    /**
     * Return count of addresses
     * 
     * @return int
     */
    public function count()
    {
        return count($this->addresses);
    }

    /**
     * Rewind iterator
     * 
     * @return void
     */
    public function rewind()
    {
        return reset($this->addresses);
    }

    /**
     * Return current item in iteration
     * 
     * @return Address
     */
    public function current()
    {
        return current($this->addresses);
    }

    /**
     * Return key of current item of iteration
     * 
     * @return string
     */
    public function key()
    {
        return key($this->addresses);
    }

    /**
     * Move to next item
     * 
     * @return void
     */
    public function next()
    {
        return next($this->addresses);
    }

    /**
     * Is the current item of iteration valid?
     * 
     * @return bool
     */
    public function valid()
    {
        $key = key($this->addresses);
        return ($key !== null && $key !== false);
    }

    /**
     * Create an address object 
     * 
     * @param  string $email 
     * @param  string|null $name 
     * @return Address
     */
    protected function createAddress($email, $name)
    {
        return new Address($email, $name);
    }
}
