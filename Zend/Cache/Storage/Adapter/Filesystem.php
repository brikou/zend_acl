<?php

namespace Zend\Cache\Storage\Adapter;
use Zend\Cache\Storage,
    Zend\Cache\Storage\Adapter,
    Zend\Cache\Storage\Capabilities,
    Zend\Cache\Utils,
    Zend\Cache\Exception\RuntimeException,
    Zend\Cache\Exception\InvalidArgumentException,
    Zend\Cache\Exception\ItemNotFoundException,
    GlobIterator;

class Filesystem extends AbstractAdapter
{

    /**
     * Overwrite default namespace pattern
     *
     * @var string
     */
    protected $_namespacePattern = '/^[a-z0-9_\+\-]*$/Di';

    /**
     * Namespace separator
     *
     * @var string
     */
    protected $_namespaceSeparator = '-';

    /**
     * Overwrite default key pattern
     */
    protected $_keyPattern = '/^[a-z0-9_\+\-]*$/Di';

    /**
     * Directory where to store cache files
     *
     * @var null|string The cache directory
     *                  or NULL for the systems temporary directory
     */
    protected $_cacheDir = null;

    /**
     * Used umask on creating a cache file
     *
     * @var int
     */
    protected $_fileUmask = -433; // ~0660

    /**
     * Lock files on writing
     *
     * @var boolean
     */
    protected $_fileLocking = true;

    /**
     * Block writing files until writing by another process finished.
     *
     * NOTE1: this only attempts if fileLocking is enabled
     * NOTE3: if disabled writing operations return false in part of a locked file
     *
     * @var boolean
     */
    protected $_fileBlocking = true;

    /**
     * Used umask on creating a cache directory
     *
     * @var int
     */
    protected $_dirUmask = -505; // ~0770

    /**
     * How much sub-directaries should be created?
     *
     * @var int
     */
    protected $_dirLevel = 1;

    /**
     * Don't get 'fileatime' as 'atime' on metadata
     *
     * @var boolean
     */
    protected $_noAtime = true;

    /**
     * Don't get 'filectime' as 'ctime' on metadata
     *
     * @var boolean
     */
    protected $_noCtime = true;

    /**
     * Read control enabled ?
     *
     * If enabled a hash (readControlAlgo) will be saved and check on read.
     *
     * @var boolean
     */
    protected $_readControl = false;

    /**
     * The used hash algorithm if read control is enabled
     *
     * @var string
     */
    protected $_readControlAlgo = 'crc32';

    /**
     * Call clearstatcache enabled?
     *
     * @var boolean
     */
    protected $_clearStatCache = true;

    /**
     * Statement
     */
    protected $_stmtGlob  = null;
    protected $_stmtMatch = null;

    /**
     * Buffer vars
     */
    protected $_lastInfoId  = null;
    protected $_lastInfoAll = null;
    protected $_lastInfo    = null;

    /* configuration */

    public function getOptions()
    {
        $options = parent::getOptions();
        $options['namespace_separator'] = $this->getNamespaceSeparator();
        $options['cache_dir']           = $this->getCacheDir();
        $options['file_perm']           = $this->getFilePerm();
        $options['file_umask']          = $this->getFileUmask();
        $options['file_locking']        = $this->getFileLocking();
        $options['file_blocking']       = $this->getFileBlocking();
        $options['dir_perm']            = $this->getDirPerm();
        $options['dir_umask']           = $this->getDirUmask();
        $options['dir_level']           = $this->getDirLevel();
        $options['no_atime']            = $this->getNoAtime();
        $options['no_ctime']            = $this->getNoCtime();
        $options['read_control']        = $this->getReadControl();
        $options['read_control_algo']   = $this->getReadControlAlgo();
        $options['clear_stat_cache']    = $this->getClearStatCache();
        return $options;
    }

    public function setNamespaceSeparator($separator)
    {
        $this->_namespaceSeparator = (string)$separator;
        $this->_updateCapabilities();
        return $this;
    }

    public function getNamespaceSeparator()
    {
        return $this->_namespaceSeparator;
    }

    public function setCacheDir($dir)
    {
        if ($dir !== null) {
            if (!is_dir($dir)) {
                throw new InvalidArgumentException(
                    "Cache directory '{$dir}' not found or not a directoy"
                );
            } elseif (!is_writable($dir)) {
                throw new InvalidArgumentException(
                    "Cache directory '{$dir}' not writable"
                );
            } elseif (!is_readable($dir)) {
                throw new InvalidArgumentException(
                    "Cache directory '{$dir}' not readable"
                );
            }

            $dir = rtrim(realpath($dir), \DIRECTORY_SEPARATOR);
        }

        $this->_cacheDir = $dir;
        return $this;
    }

    public function getCacheDir()
    {
        if ($this->_cacheDir === null) {
            $this->setCacheDir(sys_get_temp_dir());
        }

        return $this->_cacheDir;
    }

    public function setFilePerm($perm)
    {
        if (is_string($perm)) {
            $perm = octdec($perm);
        } else {
            $perm = (int)$perm;
        }

        // use umask
        return $this->setFileUmask(~$perm);
    }

    public function getFilePerm()
    {
        return ~$this->getFileUmask();
    }

    public function setFileUmask($umask)
    {
        if (is_string($umask)) {
            $umask = octdec($umask);
        } else {
            $umask = (int)$umask;
        }
        if ((~$umask & 0600) != 0600 ) {
            throw new InvalidArgumentException(
                'Invalid file umask or file permission: '
              . 'need permissions to read and write files by owner'
            );
        } elseif ((~$umask & 0111) > 0) {
            throw new InvalidArgumentException(
                'Invalid file umask or file permission: '
              . 'executable cache files are not allowed'
            );
        }

        $this->_fileUmask = $umask;
        return $this;
    }

    public function getFileUmask()
    {
        return $this->_fileUmask;
    }

    public function setFileLocking($flag)
    {
        $this->_fileLocking = (bool)$flag;
        return $this;
    }

    public function getFileLocking()
    {
        return $this->_fileLocking;
    }

    public function setFileBlocking($flag)
    {
        $this->_fileBlocking = (bool)$flag;
        return $this;
    }

    public function getFileBlocking()
    {
        return $this->_fileBlocking;
    }

    public function setNoAtime($flag)
    {
        $this->_noAtime = (bool)$flag;
        $this->_updateCapabilities();
        return $this;
    }

    public function getNoAtime()
    {
        return $this->_noAtime;
    }

    public function setNoCtime($flag)
    {
        $this->_noCtime = (bool)$flag;
        $this->_updateCapabilities();
        return $this;
    }

    public function getNoCtime()
    {
        return $this->_noCtime;
    }

    public function setDirPerm($perm)
    {
        if (is_string($perm)) {
            $perm = octdec($perm);
        } else {
            $perm = (int)$perm;
        }

        // use umask
        return $this->setDirUmask(~$perm);
    }

    public function getDirPerm()
    {
        return ~$this->getDirUmask();
    }

    public function setDirUmask($umask)
    {
        if (is_string($umask)) {
            $umask = octdec($umask);
        } else {
            $umask = (int)$umask;
        }

        if ((~$umask & 0700) != 0700 ) {
            throw new InvalidArgumentException(
                'Invalid directory umask or directory permissions: '
              . 'need permissions to execute, read and write directories by owner'
            );
        }

        $this->_dirUmask = $umask;
        return $this;
    }

    public function getDirUmask()
    {
        return $this->_dirUmask;
    }

    public function setDirLevel($level)
    {
        $level = (int)$level;
        if ($level < 0 || $level > 16) {
            throw new InvalidArgumentException(
                "Directory level '{$level}' have to be between 0 and 16"
            );
        }
        $this->_dirLevel = $level;
        return $this;
    }

    public function getDirLevel()
    {
        return $this->_dirLevel;
    }

    public function setReadControl($flag)
    {
        $this->_readControl = (bool)$flag;
        return $this;
    }

    public function getReadControl()
    {
        return $this->_readControl;
    }

    public function setReadControlAlgo($algo)
    {
        $algo = strtolower($algo);

        if (!in_array($algo, Utils::getHashAlgos())) {
            throw new InvalidArgumentException("Unsupported hash algorithm '{$algo}");
        }

        $this->_readControlAlgo = $algo;
        return $this;
    }

    public function getReadControlAlgo()
    {
        return $this->_readControlAlgo;
    }

    public function setClearStatCache($flag)
    {
        $this->_clearStatCache = (bool)$flag;
        return $this;
    }

    public function getClearStatCache()
    {
        return $this->_clearStatCache;
    }

    /* reading */

    public function getItem($key, array $options = array())
    {
        if (!$this->getReadable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = $this->_get($key, $options);
            if (array_key_exists('token', $options)) {
                // use filemtime + filesize as CAS token
                $keyInfo = $this->_getKeyInfo($key, $options['namespace']);
                $options['token'] = $keyInfo['mtime'] . filesize($keyInfo['filespec'] . '.dat');
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function getItems(array $keys, array $options = array())
    {
        if (!$this->getReadable()) {
            return array();
        }

        $this->_normalizeOptions($options);
        // don't throw ItemNotFoundException on getItems
        $options['ignore_missing_items'] = true;

        $args = new \ArrayObject(array(
            'keys'    => & $keys,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = array();
            foreach ($keys as $key) {
                if ( ($rs = $this->_get($key, $options)) !== false) {
                    $result[$key] = $rs;
                }
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function hasItem($key, array $options = array())
    {
        if (!$this->getReadable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = $this->_exists($key, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function hasItems(array $keys, array $options = array())
    {
        if (!$this->getReadable()) {
            return array();
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keys'    => & $keys,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = array();
            foreach ($keys as $key) {
                if ( $this->_exists($key, $options) === true ) {
                    $result[] = $key;
                }
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function getMetadata($key, array $options = array())
    {
        if (!$this->getReadable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $lastInfoId = $options['namespace'] . $this->getNamespaceSeparator() . $key;
            if ($this->_lastInfoId == $lastInfoId && $this->_lastInfoAll) {
                return $this->_lastInfoAll;
            }

            $this->_lastInfoAll = $result = $this->_info($key, $options);

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function getMetadatas(array $keys, array $options = array())
    {
        if (!$this->getReadable()) {
            return array();
        }

        $this->_normalizeOptions($options);
        // don't throw ItemNotFoundException on getMetadatas
        $options['ignore_missing_items'] = true;

        $args = new \ArrayObject(array(
            'keys'    => & $keys,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = array();
            foreach ($keys as $key) {
                if ( ($info = $this->_info($key, $options)) !== false ) {
                    $result[$key] = $info;
                }
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    /* writing */

    public function setItem($key, $value, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'value'   => & $value,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = $this->_set($key, $value, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function setItems(array $keyValuePairs, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keyValuePairs' => & $keyValuePairs,
            'options'       => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = true;
            foreach ($keyValuePairs as $key => $value) {
                $result = $this->_set($key, $value, $options) && $result;
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function replaceItem($key, $value, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'value'   => & $value,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            if ( !$this->_exists($key, $options) ) {
                throw new ItemNotFoundException("Key '{$key}' doesn't exist");
            }

            $result = $this->_set($key, $value, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function replaceItems(array $keyValuePairs, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keyValuePairs' => & $keyValuePairs,
            'options'       => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = true;
            foreach ($keyValuePairs as $key => $value) {
                if ( !$this->_exists($key, $options) ) {
                    throw new ItemNotFoundException("Key '{$key}' doesn't exist");
                }
                $result = $this->_set($key, $value, $options) && $result;
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function addItem($key, $value, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'value'   => & $value,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            if ( $this->_exists($key, $options) ) {
                throw new RuntimeException("Key '{$key}' already exist");
            }

            $result = $this->_set($key, $value, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function addItems(array $keyValuePairs, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keyValuePairs' => & $keyValuePairs,
            'options'       => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $result = true;
            foreach ($keyValuePairs as $key => $value) {
                if ( $this->_exists($key, $options) ) {
                    throw new RuntimeException("Key '{$key}' already exist");
                }

                $result = $this->_set($key, $value, $options) && $result;
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function checkAndSetItem($token, $key, $value, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'token'   => & $token,
            'key'     => & $key,
            'value'   => & $value,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            if ( !($keyInfo = $this->_getKeyInfo($key, $options['namespace'])) ) {
                if ($options['ignore_missing_items']) {
                    $result = false;
                } else {
                    throw new ItemNotFoundException("Key '{$key}' not found within namespace '{$options['namespace']}'");
                }
            } else {
                // use filemtime + filesize as CAS token
                $check = $keyInfo['mtime'] . filesize($keyInfo['filespec'] . '.dat');
                if ($token != $check) {
                    $result = false;
                } else {
                    $result = $this->_set($key, $value, $options);
                }
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function touchItem($key, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            $this->_touch($key, $options);
            $this->_lastInfoId = null;

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function touchItems(array $keys, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keys'    => & $keys,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            foreach ($keys as $key) {
                $this->_touch($key, $options);
            }
            $this->_lastInfoId = null;

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function removeItem($key, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $key = $this->_key($key);
        $args = new \ArrayObject(array(
            'key'     => & $key,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            // unlink is not affected by clearstatcache
            $this->_remove($key, $options);

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function removeItems(array $keys, array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'keys'    => & $keys,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            // unlink is not affected by clearstatcache
            foreach ($keys as $key) {
                $this->_remove($key, $options);
            }

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    /* non-blocking */

    public function find($mode = Adapter::MATCH_ACTIVE, array $options = array())
    {
        if ($this->_stmtActive) {
            throw new RuntimeException('Statement already in use');
        }

        if (!$this->getReadable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $this->_normalizeMatchingMode($mode, Adapter::MATCH_ACTIVE, $options);
        $options = array_merge($this->getOptions(), $options);
        $args = new \ArrayObject(array(
            'mode'    => & $mode,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->getClearStatCache()) {
                clearstatcache();
            }

            try {
                $prefix = $options['namespace'] . $this->getNamespaceSeparator();
                $find = $options['cache_dir']
                    . str_repeat(\DIRECTORY_SEPARATOR . $prefix . '*', $options['dir_level'])
                    . \DIRECTORY_SEPARATOR . $prefix . '*.dat';
                $glob = new \GlobIterator($find);

                $this->_stmtActive  = true;
                $this->_stmtGlob    = $glob;
                $this->_stmtMatch   = $mode;
                $this->_stmtOptions = $options;
            } catch (\Exception $e) {
                throw new RuntimeException('Instantiating glob iterator failed', 0, $e);
            }

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function fetch()
    {
        if (!$this->_stmtActive) {
            return false;
        }

        $args = new \ArrayObject();

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->_stmtGlob !== null) {
                $result = $this->_fetchByGlob();

                if ($result === false) {
                    // clear statement
                    $this->_stmtActive  = false;
                    $this->_stmtGlob    = null;
                    $this->_stmtMatch   = null;
                    $this->_stmtOptions = null;
                }
            } else {
                $result = parent::fetch();
            }

            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    /* cleaning */

    public function clear($mode = Adapter::MATCH_EXPIRED, array $options = array())
    {
        $this->_normalizeOptions($options);
        $this->_normalizeMatchingMode($mode, Adapter::MATCH_EXPIRED, $options);
        $args = new \ArrayObject(array(
            'mode'    => & $mode,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            $result = $this->_clearByPrefix('', $mode, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function clearByNamespace($mode = Adapter::MATCH_EXPIRED, array $options = array())
    {
        $this->_normalizeOptions($options);
        $this->_normalizeMatchingMode($mode, Adapter::MATCH_EXPIRED, $options);
        $args = new \ArrayObject(array(
            'mode'    => & $mode,
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            $prefix = $options['namespace'] . $this->getNamespaceSeparator();
            $result = $this->_clearByPrefix($prefix, $mode, $options);
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function optimize(array $options = array())
    {
        if (!$this->getWritable()) {
            return false;
        }

        $this->_normalizeOptions($options);
        $args = new \ArrayObject(array(
            'options' => & $options
        ));

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ( ($dirLevel = $this->getDirLevel()) ) {
                // removes only empty directories
                $this->_rmDir($this->getCacheDir(), $options['namespace'] . $this->getNamespaceSeparator());
            }

            $result = true;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    /* status */

    public function getCapabilities()
    {
        $args = new \ArrayObject();

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            if ($this->_capabilities === null) {
                $this->_capabilityMarker = new \stdClass();
                    $this->_capabilities = new Capabilities(
                    $this->_capabilityMarker,
                    array(
                        'supportedDatatypes' => array(
                            'NULL'     => 'string',
                            'boolean'  => 'string',
                            'integer'  => 'string',
                            'double'   => 'string',
                            'string'   => true,
                            'array'    => false,
                            'object'   => false,
                            'resource' => false,
                        ),
                        'supportedMetadata'  => array('mtime', 'filespec'),
                        'maxTtl'             => 0,
                        'staticTtl'          => false,
                        'ttlPrecision'       => 1,
                        'expiredRead'        => true,
                        'maxKeyLength'       => 251, // 255 - strlen(.dat | .ifo)
                        'namespaceIsPrefix'  => true,
                        'namespaceSeparator' => $this->getNamespaceSeparator(),
                        'iterable'           => true,
                        'clearAllNamespaces' => true,
                        'clearByNamespace'   => true,
                    )
                );

                // set dynamic capibilities
                $this->_updateCapabilities();
            }

            $result = $this->_capabilities;
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    public function getCapacity(array $options = array())
    {
        $args = new \ArrayObject();

        try {
            $eventRs = $this->triggerPre(__FUNCTION__, $args);
            if ($eventRs->stopped()) {
                return $eventRs->last();
            }

            $result = Utils::getDiskCapacity($this->getCacheDir());
            return $this->triggerPost(__FUNCTION__, $args, $result);
        } catch (Exception $e) {
            return $this->triggerException(__FUNCTION__, $args, $e);
        }
    }

    /* internal */

    protected function _set($key, $value, array &$options)
    {
        $oldUmask = null;

        $lastInfoId = $options['namespace'] . $this->getNamespaceSeparator() . $key;
        if ($this->_lastInfoId == $lastInfoId) {
            $filespec = $this->_lastInfo['filespec'];
            // if lastKeyInfo is available I'm sure that the cache directory exist
        } else {
            $filespec = $this->_getKeyFileSpec($key, $options['namespace']);
            if ($this->getDirLevel() > 0) {
                $path = dirname($filespec);
                if (!file_exists($path)) {
                    $oldUmask = umask($this->getDirUmask());
                    if ( !@mkdir($path, 0777, true) ) {
                        // reset umask on exception
                        umask($oldUmask);

                        // throw exception with last error message
                        $lastErr = error_get_last();
                        throw new RuntimeException($lastErr['message']);
                    }
                }
            }
        }

        $info = null;
        if ($this->getReadControl()) {
            $info['hash'] = Utils::generateHash($this->getReadControlAlgo(), $data, true);
            $info['algo'] = $this->getReadControlAlgo();
        }

        if (isset($options['tags']) && $options['tags']) {
            $info['tags'] = array_values(array_unique($options['tags']));
        }

        try {
            if ($oldUmask !== null) { // $oldUmask could be defined on set directory_umask
                umask($this->getFileUmask());
            } else {
                $oldUmask = umask($this->getFileUmask());
            }

            $ret = $this->_putFileContent($filespec . '.dat', $value);
            if ($ret && $info) {
                // Don't throw exception if writing of info file failed
                // -> only return false
                try {
                    $ret = $this->_putFileContent($filespec . '.ifo', serialize($info));
                } catch (\Exception $e) {
                    $ret = false;
                }
            }

            $this->_lastInfoId = null;

            // reset file_umask
            umask($oldUmask);

            return $ret;

        } catch (\Exception $e) {
            // reset umask on exception
            umask($oldUmask);
            throw $e;
        }
    }

    protected function _remove($key, array &$options)
    {
        $filespec = $this->_getKeyFileSpec($key, $options['namespace']);

        if (!$options['ignore_missing_items'] && !file_exists($filespec . '.dat')) {
            throw new ItemNotFoundException("Key '{$key}' with file '{$filespec}.dat' not found");
        }

        $this->_unlink($filespec . '.dat');
        $this->_unlink($filespec . '.ifo');
        $this->_lastInfoId = null;
    }

    protected function _get($key, array &$options)
    {
        if ( !$this->_exists($key, $options)
          || !($keyInfo=$this->_getKeyInfo($key, $options['namespace'])) ) {
            if ($options['ignore_missing_items']) {
                return false;
            } else {
                throw new ItemNotFoundException("Key '{$key}' not found within namespace '{$options['namespace']}'");
            }
        }

        try {
            $data = $this->_getFileContent($keyInfo['filespec'] . '.dat');

            if ($this->getReadControl()) {
                if ( ($info = $this->_readInfoFile($keyInfo['filespec'] . '.ifo'))
                  && isset($info['hash'], $info['algo']) ) {
                    $hashData = Utils::generateHash($info['algo'], $data, true);
                    if ($hashData != $info['hash']) {
                        throw new UnexpectedValueException(
                            'readControl: Stored hash and computed hash don\'t match'
                        );
                    }
                }
            }

            return $data;

        } catch (\Exception $e) {
            try {
                // remove cache file on exception
                $this->_remove($key, $options);
            } catch (\Exception $tmp) {} // do not throw remove exception on this point

            throw $e;
        }
    }

    protected function _exists($key, array &$options)
    {
        $keyInfo = $this->_getKeyInfo($key, $options['namespace']);
        if (!$keyInfo) {
            return false; // missing or corrupted cache data
        }

        if ( !$options['ttl']      // infinite lifetime
          || time() < ($keyInfo['mtime'] + $options['ttl'])  // not expired
        ) {
            return true;
        }

        return false;
    }

    protected function _info($key, array &$options) {
        $keyInfo = $this->_getKeyInfo($key, $options['namespace']);
        if (!$keyInfo) {
            if ($options['ignore_missing_items']) {
                return false;
            } else {
                throw new ItemNotFoundException("Key '{$key}' not found within namespace '{$options['namespace']}'");
            }
        }

        if ( ($info = $this->_readInfoFile($keyInfo['filespec'] . '.ifo')) ) {
            return $keyInfo + $info;
        }

        return $keyInfo;
    }

    protected function _touch($key, array &$options)
    {
        $keyInfo = $this->_getKeyInfo($key, $options['namespace']);
        if (!$keyInfo) {
            if ($options['ignore_missing_items']) {
                return false;
            } else {
                throw new ItemNotFoundException("Key '{$key}' not found within namespace '{$options['namespace']}'");
            }
        }

        if ( !@touch($keyInfo['filespec'] . '.dat') ) {
            $err = error_get_last();
            throw new RuntimeException($err['message']);
        }
    }

    protected function _fetchByGlob()
    {
        $options = $this->_stmtOptions;
        $mode    = $this->_stmtMatch;

        $prefix  = $options['namespace'] . $this->getNamespaceSeparator();
        $prefixL = strlen($prefix);

        do {
            try {
                $valid = $this->_stmtGlob->valid();
            } catch (\LogicException $e) {
                // @link https://bugs.php.net/bug.php?id=55701
                // GlobIterator throws LogicException with message
                // 'The parent constructor was not called: the object is in an invalid state'
                $valid = false;
            }
            if (!$valid) {
                return false;
            }

            $item = array();
            $info = null;

            $current = $this->_stmtGlob->current();
            $this->_stmtGlob->next();

            $filename = $current->getFilename();
            if ($prefix !== '') {
                if (substr($filename, 0, $prefixL) != $prefix) {
                    continue;
                }

                // remove prefix and suffix (.dat)
                $key = substr($filename, $prefixL, -4);
            } else {
                // remove suffix (.dat)
                $key = substr($filename, 0, -4);
            }

            // if MATCH_ALL mode do not check expired
            if (($mode & self::MATCH_ALL) != self::MATCH_ALL) {
                $mtime = $current->getMTime();

                // if MATCH_EXPIRED -> filter not expired items
                if (($mode & self::MATCH_EXPIRED) == self::MATCH_EXPIRED) {
                    if ( time() < ($mtime + $options['ttl']) ) {
                        continue;
                    }

                // if MATCH_ACTIVE -> filter expired items
                } else {
                    if ( time() >= ($mtime + $options['ttl']) ) {
                        continue;
                    }
                }
            }

            // check tags only if one of the tag matching mode is selected
            if (($mode & 070) > 0) {

                $info = $this->_info($key, $options);

                // if MATCHING_TAGS mode -> check if all given tags available in current cache
                if (($mode & self::MATCHING_TAGS) == self::MATCHING_TAGS ) {
                    if (!isset($info['tags']) || count(array_diff($opts['tags'], $info['tags'])) > 0) {
                        continue;
                    }

                // if MATCHING_NO_TAGS mode -> check if no given tag available in current cache
                } elseif( ($mode & self::MATCHING_NO_TAGS) == self::MATCHING_NO_TAGS ) {
                    if (isset($info['tags']) && count(array_diff($opts['tags'], $info['tags'])) != count($opts['tags'])) {
                        continue;
                    }

                // if MATCHING_ANY_TAGS mode -> check if any given tag available in current cache
                } elseif ( ($mode & self::MATCHING_ANY_TAGS) == self::MATCHING_ANY_TAGS ) {
                    if (!isset($info['tags']) || count(array_diff($opts['tags'], $info['tags'])) == count($opts['tags'])) {
                        continue;
                    }

                }
            }

            foreach ($options['select'] as $select) {
                if ($select == 'key') {
                    $item['key'] = $key;
                } else if ($select == 'value') {
                    $item['value'] = $this->_getFileContent($current->getPathname());
                } else if ($select != 'key') {
                    if ($info === null) {
                        $info = $this->_info($key, $options);
                    }
                    $item[$select] = isset($info[$select]) ? $info[$select] : null;
                }
            }

            return $item;
        } while (true);
    }

    protected function _clearByPrefix($prefix, $mode, array &$opts)
    {
        if (!$this->getWritable()) {
            return false;
        }

        $ttl = $opts['ttl'];

        if ($this->getClearStatCache()) {
            clearstatcache();
        }

        try {
            $find = $this->getCacheDir()
                . str_repeat(\DIRECTORY_SEPARATOR . $prefix . '*', $this->getDirLevel())
                . \DIRECTORY_SEPARATOR . $prefix . '*.dat';
            $glob = new \GlobIterator($find);
        } catch (\Exception $e) {
            throw new RuntimeException('Instantiating GlobIterator failed', 0, $e);
        }

        $time = time();

        foreach ($glob as $entry) {

            // if MATCH_ALL mode do not check expired
            if (($mode & Adapter::MATCH_ALL) != Adapter::MATCH_ALL) {

                $mtime = $entry->getMTime();
                if (($mode & Adapter::MATCH_EXPIRED) == Adapter::MATCH_EXPIRED) {
                    if ( $time <= ($mtime + $ttl) ) {
                        continue;
                    }

                // if Zend_Cache::MATCHING_ACTIVE mode selected do not remove expired data
                } else {
                    if ( $time >= ($mtime + $ttl) ) {
                        continue;
                    }
                }
            }

            // remove file suffix (*.dat)
            $pathnameSpec = substr($entry->getPathname(), 0, -4);

            ////////////////////////////////////////
            // on this time all expire tests match
            ////////////////////////////////////////

            // check tags only if one of the tag matching mode is selected
            if (($mode & 070) > 0) {

                $info = $this->_readInfoFile($filespec . '.ifo');

                // if MATCHING_TAGS mode -> check if all given tags available in current cache
                if (($mode & Adapter::MATCHING_TAGS) == Adapter::MATCHING_TAGS ) {
                    if (!isset($info['tags']) || count(array_diff($opts['tags'], $info['tags'])) > 0) {
                        continue;
                    }

                // if MATCHING_NO_TAGS mode -> check if no given tag available in current cache
                } elseif( ($mode & Adapter::MATCHING_NO_TAGS) == Adapter::MATCHING_NO_TAGS ) {
                    if (isset($info['tags']) && count(array_diff($opts['tags'], $info['tags'])) != count($opts['tags'])) {
                        continue;
                    }

                // if MATCHING_ANY_TAGS mode -> check if any given tag available in current cache
                } elseif ( ($mode & Adapter::MATCHING_ANY_TAGS) == Adapter::MATCHING_ANY_TAGS ) {
                    if (!isset($info['tags']) || count(array_diff($opts['tags'], $info['tags'])) == count($opts['tags'])) {
                        continue;
                    }

                }
            }

            ////////////////////////////////////////
            // on this time all tests match
            ////////////////////////////////////////

            $this->_unlink($pathnameSpec . '.dat'); // delete data file
            $this->_unlink($pathnameSpec . '.ifo'); // delete info file
        }

        return true;
    }

    /**
     * Removes directories recursive by namespace
     *
     * @param string $dir    Directory to delete
     * @param string $prefix Namespace + Separator
     */
    protected function _rmDir($dir, $prefix)
    {
        $glob = glob(
            $dir . \DIRECTORY_SEPARATOR . $prefix  . '*',
            \GLOB_ONLYDIR | \GLOB_NOESCAPE | \GLOB_NOSORT
        );
        if (!$glob) {
            // On some systems glob returns false even on empty result
            return true;
        }

        $ret = true;
        foreach ($glob as $subdir) {
            // ignore not empty directories
            // skip removing current directory if removing of sub-directory failed
            $ret = $this->_rmDir($subdir, $prefix) && @rmdir($subdir);
        }
        return $ret;
    }

    /**
     * Get an array of information about the cache key.
     * NOTE: returns false if cache doesn't hit.
     *
     * @param string $key
     * @param string $ns
     * @return array|boolean
     */
    protected function _getKeyInfo($key, $ns)
    {
        $lastInfoId = $ns . $this->getNamespaceSeparator() . $key;
        if ($this->_lastInfoId == $lastInfoId) {
            return $this->_lastInfo;
        }

        $filespec = $this->_getKeyFileSpec($key, $ns);

        if ( ($filemtime = @filemtime($filespec . '.dat')) === false ) {
            return false;
        }

        $this->_lastInfoId  = $lastInfoId;
        $this->_lastInfoAll = null;
        $this->_lastInfo    = array(
            'filespec' => $filespec,
            'mtime'    => $filemtime
        );

        if (!$this->getNoCtime()) {
            $this->_lastInfo['ctime'] = filectime($filespec . '.dat');
        }

        if (!$this->getNoAtime()) {
            $this->_lastInfo['atime'] = fileatime($filespec . '.dat');
        }

        return $this->_lastInfo;
    }

    /**
     * Get cache file spec
     *
     * @param string $key
     * @param string $ns
     * @return string
     */
    protected function _getKeyFileSpec($key, $ns)
    {
        $prefix = $ns . $this->getNamespaceSeparator();
        $lastInfoId = $prefix . $key;
        if ($this->_lastInfoId == $lastInfoId) {
            return $this->_lastInfo['filespec'];
        }

        $path  = $this->getCacheDir();
        $level = $this->getDirLevel();
        if ( $level > 0 ) {
            // create up to 256 directories per directory level
            $hash = md5($key);
            for ($i = 0, $max = ($level * 2); $i < $max; $i+= 2) {
                $path.= \DIRECTORY_SEPARATOR . $prefix . $hash[$i] . $hash[$i+1];
            }
        }

        return $path . \DIRECTORY_SEPARATOR . $prefix . $key;
    }

    /**
     * Read info file
     *
     * @param string $file
     * @return array|boolean The info array or false if file wasn't found
     * @throws RuntimeException
     */
    protected function _readInfoFile($file) {
        if ( file_exists($file) ) {
            $info = @unserialize($this->_getFileContent($file));
            if (!is_array($info)) {
               $err = error_get_last();
               throw new RuntimeException("Corrupted info file '{$file}': {$err['message']}");
            }
            return $info;
        }

        return false;
    }

    /**
     * Read a complete file
     *
     * @param  string $file File complete path
     * @throws RuntimeException
     */
    protected function _getFileContent($file)
    {
        // if file locking enabled -> file_get_contents can't be used
        if ($this->getFileLocking()) {
            $fp = @fopen($file, 'rb');
            if ($fp === false) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }

            if (!flock($fp, \LOCK_SH)) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }

            $result = @stream_get_contents($fp);
            if ($result === false) {
                $lastErr = error_get_last();
                @flock($fp, \LOCK_UN);
                @fclose($fp);
                throw new RuntimeException($lastErr['message']);
            }

            flock($fp, \LOCK_UN);
            fclose($fp);

        // if file locking disabled -> file_get_contents can be used
        } else {
            $result = @file_get_contents($file, false);
            if ($result === false) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }
        }

        return $result;
    }

    /**
     * Write content to a file
     *
     * @param  string $file  File complete path
     * @param  string $data  Data to write
     * @throws RuntimeException
     */
    protected function _putFileContent($file, $data)
    {
        $locking  = $this->getFileLocking();
        $blocking = $locking ? $this->getFileBlocking() : false;

        if ($locking && !$blocking) {
            $fp = @fopen($file, 'cb');
            if (!$fp) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }

            if(!flock($fp, \LOCK_EX | \LOCK_NB)) {
                // file is locked by another process -> aborting writing
                fclose($fp);
                return false;
            }

            if (!ftruncate($fp, 0)) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }

            if (!fwrite($fp, $data)) {
                 $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }

            flock($fp, \LOCK_UN);
            fclose($fp);
        } else {
            $flags = 0;
            if ($locking) {
                $flags = $flags | \LOCK_EX;
            }

            if ( @file_put_contents($file, $data, $flags) === false ) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }
        }

        return true;
    }

    /**
     * Unlink a file
     *
     * @param string $file
     * @throw RuntimeException
     */
    protected function _unlink($file) {
        if (!@unlink($file)) {
            // only throw exception if file still exists after deleting
            if (file_exists($file)) {
                $lastErr = error_get_last();
                throw new RuntimeException($lastErr['message']);
            }
        }
    }

    /**
     * Update dynamic capabilities only if already created
     */
    protected function _updateCapabilities()
    {
        if ($this->_capabilities) {

            // update namespace separator
            $this->_capabilities->setNamespaceSeparator(
                $this->_capabilityMarker,
                $this->getNamespaceSeparator()
            );

            // update metadata capabilities
            $metadata = array('mtime', 'filespec');

            if (!$this->getNoCtime()) {
                $metadata[] = 'ctime';
            }

            if (!$this->getNoAtime()) {
                $metadata[] = 'atime';
            }

            $this->_capabilities->setSupportedMetadata(
                $this->_capabilityMarker,
                $metadata
            );
        }
    }

}
