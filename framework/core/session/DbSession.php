<?php

namespace rock\session;

use rock\db\Connection;
use rock\db\Query;
use rock\exception\ErrorHandler;
use rock\Rock;

/**
 * DbSession extends [[Session]] by using database as session data storage.
 *
 * By default, DbSession stores session data in a DB table named 'session'. This table
 * must be pre-created. The table name can be changed by setting [[sessionTable]].
 *
 * The following example shows how you can configure the application to use DbSession:
 * Add the following to your application config under `components`:
 *
 * ~~~
 * 'session' => [
 *     'class' => DbSession::className(),
 *     'db' => 'mydb',
 *     'sessionTable' => 'session_table',
 * ]
 * ~~~
 */
class DbSession extends Session
{
    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     * After the DbSession object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     */
    public $db = 'db';
    /**
     * @var string the name of the DB table that stores the session data.
     * The table should be pre-created as follows:
     *
     * ~~~
     * CREATE TABLE sessions
     * (
     *     id CHAR(40) NOT NULL PRIMARY KEY,
     *     expire INTEGER,
     *     data BLOB
     * )
     * ~~~
     *
     * where 'BLOB' refers to the BLOB-type of your preferred DBMS. Below are the BLOB type
     * that can be used for some popular DBMS:
     *
     * - MySQL: LONGBLOB
     * - PostgreSQL: BYTEA
     * - MSSQL: BLOB
     *
     * When using DbSession in a production server, we recommend you create a DB index for the 'expire'
     * column in the session table to improve the performance.
     */
    public $sessionTable = '{{%sessions}}';

    /**
     * Initializes the DbSession component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws Exception if [[db]] is invalid.
     */
    public function init()
    {
        if (!$this->db instanceof Connection) {
            $this->db = Rock::factory($this->db);
        }
    }

    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return boolean whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return true;
    }

    /**
     * Updates the current session ID with a newly generated one .
     * Please refer to <http://php.net/session_regenerate_id> for more details.
     * @param boolean $deleteOldSession Whether to delete the old associated session file or not.
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldID = session_id();

        // if no session is started, there is nothing to regenerate
        if (empty($oldID)) {
            return;
        }

        parent::regenerateID(false);
        $newID = session_id();


        $row = (new Query)->from($this->sessionTable)
            ->where(['id' => $oldID])
            ->createCommand($this->db)
            ->queryOne();
        if ($row !== false) {
            if ($deleteOldSession) {
                $this->db->createCommand()
                    ->update($this->sessionTable, ['id' => $newID], ['id' => $oldID])
                    ->execute();
            } else {
                $row['id'] = $newID;
                $this->db->createCommand()
                    ->insert($this->sessionTable, $row)
                    ->execute();
            }
        } else {
            // shouldn't reach here normally
            $this->db->createCommand()
                ->insert($this->sessionTable, [
                                                'id' => $newID,
                                                'expire' => time() + $this->getTimeout(),
                                            ])->execute();
        }
    }

    /**
     * Session read handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return string the session data
     */
    public function readSession($id)
    {
        $data = (new Query)
            ->select(['data'])
            ->from($this->sessionTable)
            ->where('[[expire]]>:expire AND [[id]]=:id', [':expire' => time(), ':id' => $id])
            ->createCommand($this->db)
            ->queryScalar();

        return $data === false ? '' : $data;
    }

    /**
     * Session write handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return boolean whether session write is successful
     */
    public function writeSession($id, $data)
    {
        // exception must be caught in session write handler
        // http://us.php.net/manual/en/function.session-set-save-handler.php
        try {
            $expire = time() + $this->getTimeout();

            $exists = (new Query)
                ->select(['id'])
                ->from($this->sessionTable)
                ->where(['id' => $id])
                ->createCommand($this->db)
                ->queryScalar();
            if ($exists === false) {
                $this->db
                    ->createCommand()
                    ->insert(
                        $this->sessionTable,
                        [
                            'id' => $id,
                            'data' => $data,
                            'expire' => $expire,
                        ]
                    )
                    ->execute();
            } else {
                $this->db->createCommand()
                    ->update($this->sessionTable, ['data' => $data, 'expire' => $expire], ['id' => $id])
                    ->execute();
            }
        } catch (\Exception $e) {
            Rock::info(ErrorHandler::convertExceptionToString($e));
            return false;
        }

        return true;
    }

    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return boolean whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $this->db->createCommand()
            ->delete($this->sessionTable, ['id' => $id])
            ->execute();

        return true;
    }

    /**
     * Session GC (garbage collection) handler.
     * Do not call this method directly.
     * @param integer $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return boolean whether session is GCed successfully
     */
    public function gcSession($maxLifetime)
    {
        $this->db->createCommand()
            ->delete($this->sessionTable, '[[expire]]<:expire', [':expire' => time()])
            ->execute();

        return true;
    }
} 