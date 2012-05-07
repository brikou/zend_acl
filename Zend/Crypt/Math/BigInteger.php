<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Package
 */

namespace Zend\Crypt\Math;

use Zend\Crypt\Math\BigInteger\Exception\InvalidArgumentException;

/**
 * Support for arbitrary precision mathematics in PHP.
 *
 * Zend_Crypt_Math_BigInteger is a wrapper across three PHP extensions: bcmath, gmp
 * and big_int. Since each offer similar functionality, but availability of
 * each differs across installations of PHP, this wrapper attempts to select
 * the fastest option available and encapsulate a subset of its functionality
 * which all extensions share in common.
 *
 * This class requires one of the three extensions to be available. BCMATH
 * while the slowest, is available by default under Windows, and under Unix
 * if PHP is compiled with the flag "--enable-bcmath". GMP requires the gmp
 * library from http://www.swox.com/gmp/ and PHP compiled with the "--with-gmp"
 * flag. BIG_INT support is available from a big_int PHP library available from
 * only from PECL (a Windows port is not available).
 *
 * @category   Zend
 * @package    Zend_Crypt
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class BigInteger implements BigInteger\BigIntegerCapableInterface
{
    /**
     * Holds an instance of one of the three arbitrary precision wrappers.
     *
     * @var \Zend\Crypt\Math\BigInteger\BigIntegerCapableInterface
     */
    protected $_math = null;

    /**
     * Constructor; a Factory which detects a suitable PHP extension for
     * arbitrary precision math and instantiates the suitable wrapper
     * object.
     *
     * @param  string $extension
     * @throws InvalidArgumentException
     */
    public function __construct($extension = null)
    {
        if ($extension !== null && !in_array($extension, array('bcmath', 'gmp', 'bigint'))) {
            throw new InvalidArgumentException('Invalid extension type; please use one of bcmath, gmp or bigint');
        }
        $this->_loadAdapter($extension);
    }

    /**
     * Redirect all unrecognized public method calls to the wrapped extension object.
     *
     * @param   string $methodName
     * @param   array $args
     * @throws  InvalidArgumentException
     */
    public function __call($methodName, $args)
    {
        if(!method_exists($this->_math, $methodName)) {
            throw new InvalidArgumentException(
                'Invalid method call: ' . get_class($this->_math) . '::' . $methodName . '() does not exist'
            );
        }
        return call_user_func_array(array($this->_math, $methodName), $args);
    }

    /**
     * @param  mixed $operand 
     * @param  int $base 
     * @return void
     */
    public function init($operand, $base = 10)
    {
        return $this->_math->init($operand, $base);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return string
     */
    public function add($left_operand, $right_operand)
    {
        return $this->_math->add($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return string
     */
    public function subtract($left_operand, $right_operand)
    {
        return $this->_math->subtract($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return integer
     */
    public function compare($left_operand, $right_operand)
    {
        return $this->_math->compare($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return string
     */
    public function divide($left_operand, $right_operand)
    {
        return $this->_math->divide($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  integer $modulus 
     * @return string
     */
    public function modulus($left_operand, $modulus)
    {
        return $this->_math->modulus($left_operand, $modulus);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return string
     */
    public function multiply($left_operand, $right_operand)
    {
        return $this->_math->multiply($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @return string
     */
    public function pow($left_operand, $right_operand)
    {
        return $this->_math->pow($left_operand, $right_operand);
    }

    /**
     * @param  string $left_operand 
     * @param  string $right_operand 
     * @param  integer $modulus 
     * @return string
     */
    public function powmod($left_operand, $right_operand, $modulus)
    {
        return $this->_math->powmod($left_operand, $right_operand, $modulus);
    }

    /**
     * @param  string $operand 
     * @return string
     */
    public function sqrt($operand)
    {
        return $this->_math->sqrt($operand);
    }

    /**
     * @param  string $operand 
     * @return integer
     */
    public function binaryToInteger($operand)
    {
        return $this->_math->binaryToInteger($operand);
    }

    /**
     * @param  integer $operand 
     * @return string
     */
    public function integerToBinary($operand)
    {
        return $this->_math->integerToBinary($operand);
    }

    /**
     * @param  string $operand 
     * @return float
     */
    public function hexToDecimal($operand)
    {
        return $this->_math->hexToDecimal($operand);
    }

    /**
     * @param  string $extension
     * @throws InvalidArgumentException
     */
    protected function _loadAdapter($extension = null)
    {
        if ($extension === null) {
            if (extension_loaded('gmp')) {
                $extension = 'gmp';
            } else {
                $extension = 'bcmath';
            }
        }
        if($extension == 'gmp' && extension_loaded('gmp')) {
            $this->_math = new BigInteger\Gmp();
        } elseif ($extension == 'bcmath' && extension_loaded('bcmath')) {
            $this->_math = new BigInteger\Bcmath();
        } else {
            throw new InvalidArgumentException($extension . ' big integer precision math support not detected');
        }
    }
}
