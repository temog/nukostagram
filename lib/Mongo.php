<?php
require(__DIR__  . '/Init.php');

class M
{
    private static $db = null;
    private static $dbName = 'nukostagram';

    public static $upsertedCount = 0;

    private static function mongo()
    {
        if (! self::$db) {
            $server = "mongodb://localhost";
            self::$db = new MongoDB\Client($server);
        }

        return self::$db->selectDatabase(self::$dbName);
    }

    public static function find($where = [], $options = [])
    {
        $where = self::convertWhere($where);
        $col = self::mongo()->{static::$collection};

        if (! $col->count($where, $options)) {
            return false;
        }
        return $col->find($where, $options);
    }

    public static function findFirst($where, $options = [])
    {
        $where = self::convertWhere($where);

        $col = self::mongo()->{static::$collection};
        if (! $col->count($where, $options)) {
            return false;
        }
        return $col->findOne($where, $options);
    }
    
    public static function count($where = [], $options = [])
    {
        $where = self::convertWhere($where);

        $col = self::mongo()->{static::$collection};
        return $col->count($where, $options);
    }
    
    public static function insert($data)
    {
        $now = time();
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        
        $col = self::mongo()->{static::$collection};

        $result = $col->insertOne($data);

        if (! $result->getInsertedCount()) {
            self::dbLog([__METHOD__, static::$collection, $data]);
            return false;
        }
        return (string) $result->getInsertedId();
    }


    // アトミックに検索してupdate
    public static function findOneUpdate($where, $data)
    {
        $where = self::convertWhere($where);

        $data['updated_at'] = time();

        try {
            $col = self::mongo()->{static::$collection};
            $result = $col->findOneAndUpdate($where, ['$set' => $data]);
            if (! $result) {
                return false;
            }
        } catch (Exception $e) {
            self::dbLog([
                __METHOD__,
                static::$collection,
                $where,
                $data,
                $result,
                $e->getMessage()
            ]);
            return false;
        }

        return true;
    }

    /*
        なかったら insert あったら update する便利メソッド
        insert で getUpsertedCount に追加
        update で getModifiedCount に追加
    */
    public static function upsert($where, $data)
    {
        $where = self::convertWhere($where);
        $now = time();
        $data['updated_at'] = $now;

        $col = self::mongo()->{static::$collection};

        $result = $col->updateMany(
            $where,
            ['$set' => $data, '$setOnInsert' => ['created_at' => $now]],
            ['upsert' => true]
        );

        self::dbLog($result);

        self::$upsertedCount = $result->getUpsertedCount();

        if (! $result->getUpsertedCount() &&
            ! $result->getModifiedCount() &&
            ! $result->getMatchedCount()) {
            self::dbLog([__METHOD__, static::$collection, $where, $data, $result]);
            return false;
        }
        return true;
    }

    public static function delete($where)
    {
        $where = self::convertWhere($where);

        $col = self::mongo()->{static::$collection};
        $result = $col->deleteMany($where);

        if (! $result->getDeletedCount()) {
            self::dbLog([__METHOD__, static::$collection, $where]);
            return false;
        }
        return true;
    }

    private static function convertWhere($where)
    {
        if (isset($where['_id'])) {
            try {
                $where['_id'] = new MongoDB\BSON\ObjectID($where['_id']);
            } catch (Exception $e) {
                $where['_id'] = null;
                self::dbLog([__METHOD__, '_id invalid format', $where]);
            }
        }
        foreach ($where as $key => $val) {
            if (is_array($val) && key($val) === '$regex') {
                $index = key($val);
                $where[$key] = new MongoDB\BSON\Regex(
                    $val[$index][0],
                    $val[$index][1]
                );
            }
        }
        return $where;
    }

    public static function findDelete($where)
    {
        try {
            $col = self::mongo()->{static::$collection};
            if (! $col->findOneAndDelete($where)) {
                return false;
            }
        } catch (Exception $e) {
            self::dbLog([__METHOD__, static::$collection, $where, $e->getMessage()]);
            return false;
        }
        return true;
    }

    private static function dbLog($log)
    {
        $file = __DIR__ . '/../log/db_' . date("Y-m-d") . '.log';
        file_put_contents($file, date("Y-m-d H:i:s") . ' ' . print_r($log, true) . "\n", FILE_APPEND | LOCK_EX);
    }
}
