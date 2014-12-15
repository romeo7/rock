<?php

namespace rockunit\core\db;


use rock\db\Connection;
use rock\db\Migration;
use rock\di\Container;
use rock\helpers\ArrayHelper;
use rock\Rock;

class DatabaseTestCase extends \PHPUnit_Framework_TestCase
{
    public static $params;
    protected $database;
    protected $driverName = 'mysql';
    /**
     * @var Connection|string
     */
    protected $connection = 'db';

    protected function setUp()
    {
        parent::setUp();
        $databases = static::getParam('databases');
        $this->database = $databases[$this->driverName];
        $pdo_database = 'pdo_'.$this->driverName;

        if (!extension_loaded('pdo') || !extension_loaded($pdo_database)) {
            $this->markTestSkipped('pdo and '.$pdo_database.' extension are required.');
        }
        //$this->mockApplication();

        //throw new \Exception('PDO not exists.');
    }

    protected function tearDown()
    {
        if ($this->connection instanceof Connection) {
            $this->connection->close();
        }
        //$this->destroyApplication();
    }

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name    params name
     * @param  mixed  $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null)
    {
        if (static::$params === null) {
            static::$params = require(Rock::getAlias('@tests/data/config.php'));
        }

        return isset(static::$params[$name]) ? static::$params[$name] : $default;
    }

    /**
     * @param  boolean            $reset whether to clean up the test database
     * @param  boolean            $open  whether to open and populate test database
     * @return Connection
     */
    public function getConnection($reset = true, $open = true)
    {
        if (!$reset && $this->connection instanceof Connection) {
            return $this->connection;
        }

        $config = ArrayHelper::intersectByKeys($this->database, ['dsn', 'username', 'password', 'attributes']);
        $config['class'] = Connection::className();
        if (is_string($this->connection)) {
            Container::add($this->connection, $config);
        }
        /** @var Connection $connection */
        $connection = Container::load($config);
        if ($open) {
            $connection->open();
            $lines = explode(';', file_get_contents($this->database['fixture']));
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $connection->pdo->exec($line);
                }
            }
            if (isset($this->database['migrations'])) {
                /** @var Migration $migration */
                foreach ($this->database['migrations'] as $migration) {
                    if (is_string($migration)) {
                        $migration = new $migration;
                    }
                    $migration->connection = $connection;
                    $migration->enableVerbose = false;
                    $migration->up();
                }
            }
        }
        $this->connection = $connection;

        return $connection;
    }
} 