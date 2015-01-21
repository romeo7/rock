<?php

namespace rockunit\extensions\mongodb;

use rock\access\Access;
use rock\event\Event;
use rock\helpers\Trace;
use rock\mongodb\ActiveQuery;
use rockunit\common\CommonTestTrait;
use rockunit\extensions\mongodb\models\ActiveRecord;
use rockunit\extensions\mongodb\models\Customer;

/**
 * @group mongodb
 */
class ActiveRecordTest extends MongoDbTestCase
{
    use CommonTestTrait;

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::getCache()->flush();
        static::disableCache();
        static::clearRuntime();
    }

    /**
     * @var array[] list of test rows.
     */
    protected $testRows = [];

    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$connection = $this->getConnection();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection(Customer::collectionName());
        parent::tearDown();
        static::disableCache();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'name' => 'name' . $i,
                'email' => 'email' . $i,
                'address' => 'address' . $i,
                'status' => $i,
            ];
        }
        $collection->batchInsert($rows);
        $this->testRows = $rows;
    }

    // Tests :

    public function testFind()
    {
        static::getCache();
        Trace::removeAll();

        // find one
        $result = Customer::find();
        $this->assertTrue($result instanceof ActiveQuery);
        $customer = $result->beginCache()->one();
        $this->assertTrue($customer instanceof Customer);

        // cache
        $this->assertFalse(Trace::getIterator('mongodb.query')->current()['cache']);
        $customer = $result->beginCache()->one();
        $this->assertTrue($customer instanceof Customer);
        $this->assertTrue(Trace::getIterator('mongodb.query')->current()['cache']);

        Trace::removeAll();
        $result->where(['name' => 'unexisting name'])->beginCache()->one();
        $this->assertFalse(Trace::getIterator('mongodb.query')->current()['cache']);
        $customer = $result->where(['name' => 'unexisting name'])->beginCache()->one();
        $this->assertNull($customer);
        $this->assertTrue(Trace::getIterator('mongodb.query')->current()['cache']);
        static::disableCache();

        // find all
        $customers = Customer::find()->all();
        $this->assertEquals(10, count($customers));
        $this->assertTrue($customers[0] instanceof Customer);
        $this->assertTrue($customers[1] instanceof Customer);

        // find by _id
        $testId = $this->testRows[0]['_id'];
        $customer = Customer::findOne($testId);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($testId, $customer->_id);

        // find by column values
        $customer = Customer::findOne(['name' => 'name5']);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($this->testRows[4]['_id'], $customer->_id);
        $this->assertEquals('name5', $customer->name);
        $customer = Customer::findOne(['name' => 'unexisting name']);
        $this->assertNull($customer);

        // find by attributes
        $customer = Customer::find()->where(['status' => 4])->one();
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals(4, $customer->status);

        // find count, sum, average, min, max, distinct
        $this->assertEquals(10, Customer::find()->count());
        $this->assertEquals(1, Customer::find()->where(['status' => 2])->count());
        $this->assertEquals((1+10)/2*10, Customer::find()->sum('status'));

        //cache
        static::getCache();
        Trace::removeAll();
        $this->assertEquals((1+10)/2, Customer::find()->beginCache()->average('status'));
        $this->assertFalse(Trace::getIterator('mongodb.query')->current()['cache']);

        $this->assertEquals((1+10)/2, Customer::find()->beginCache()->average('status'));
        $this->assertTrue(Trace::getIterator('mongodb.query')->current()['cache']);
        static::disableCache();

        $this->assertEquals(1, Customer::find()->min('status'));
        $this->assertEquals(10, Customer::find()->max('status'));
        $this->assertEquals(range(1, 10), Customer::find()->distinct('status'));

        // scope
        $this->assertEquals(1, Customer::find()->activeOnly()->count());

        // asArray
        $testRow = $this->testRows[2];
        $customer = Customer::find()->where(['_id' => $testRow['_id']])->asArray()->one();
        $this->assertEquals($testRow, $customer);

        // indexBy
        $customers = Customer::find()->indexBy('name')->all();
        $this->assertTrue($customers['name1'] instanceof Customer);
        $this->assertTrue($customers['name2'] instanceof Customer);

        // indexBy callable
        $customers = Customer::find()->indexBy(function ($customer) {
            return $customer->status . '-' . $customer->status;
        })->all();
        $this->assertTrue($customers['1-1'] instanceof Customer);
        $this->assertTrue($customers['2-2'] instanceof Customer);
    }

    public function testInsert()
    {
        $record = new Customer;
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;

        $this->assertTrue($record->isNewRecord);

        $record->save();

        $this->assertTrue($record->_id instanceof \MongoId);
        $this->assertFalse($record->isNewRecord);
    }

    /**
     * @depends testInsert
     */
    public function testUpdate()
    {
        $record = new Customer;
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        // save
        $record = Customer::findOne($record->_id);
        $this->assertTrue($record instanceof Customer);
        $this->assertEquals(7, $record->status);
        $this->assertFalse($record->isNewRecord);

        $record->status = 9;
        $record->save();
        $this->assertEquals(9, $record->status);
        $this->assertFalse($record->isNewRecord);
        $record2 = Customer::findOne($record->_id);
        $this->assertEquals(9, $record2->status);

        // updateAll
        $pk = ['_id' => $record->_id];
        $ret = Customer::updateAll(['status' => 55], $pk);
        $this->assertEquals(1, $ret);
        $record = Customer::findOne($pk);
        $this->assertEquals(55, $record->status);
    }

    /**
     * @depends testInsert
     */
    public function testDelete()
    {
        // delete
        $record = new Customer;
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        $record = Customer::findOne($record->_id);
        $record->delete();
        $record = Customer::findOne($record->_id);
        $this->assertNull($record);

        // deleteAll
        $record = new Customer;
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = 'new address';
        $record->status = 7;
        $record->save();

        $ret = Customer::deleteAll(['name' => 'new name']);
        $this->assertEquals(1, $ret);
        $records = Customer::find()->where(['name' => 'new name'])->all();
        $this->assertEquals(0, count($records));
    }

    public function testUpdateAllCounters()
    {
        $this->assertEquals(1, Customer::updateAllCounters(['status' => 10], ['status' => 10]));

        $record = Customer::findOne(['status' => 10]);
        $this->assertNull($record);
    }

    /**
     * @depends testUpdateAllCounters
     */
    public function testUpdateCounters()
    {
        $record = Customer::findOne($this->testRows[9]);

        $originalCounter = $record->status;
        $counterIncrement = 20;
        $record->updateCounters(['status' => $counterIncrement]);
        $this->assertEquals($originalCounter + $counterIncrement, $record->status);

        $refreshedRecord = Customer::findOne($record->_id);
        $this->assertEquals($originalCounter + $counterIncrement, $refreshedRecord->status);
    }

    /**
     * @depends testUpdate
     */
    public function testUpdateNestedAttribute()
    {
        $record = new Customer;
        $record->name = 'new name';
        $record->email = 'new email';
        $record->address = [
            'city' => 'SomeCity',
            'street' => 'SomeStreet',
        ];
        $record->status = 7;
        $record->save();

        // save
        $record = Customer::findOne($record->_id);
        $newAddress = [
            'city' => 'AnotherCity'
        ];
        $record->address = $newAddress;
        $record->save();
        $record2 = Customer::findOne($record->_id);
        $this->assertEquals($newAddress, $record2->address);
    }

    /**
     * @depends testFind
     * @depends testInsert
     */
    public function testQueryByIntegerField()
    {
        $record = new Customer;
        $record->name = 'new name';
        $record->status = 7;
        $record->save();

        $row = Customer::find()->where(['status' => 7])->one();
        $this->assertNotEmpty($row);
        $this->assertEquals(7, $row->status);

        $rowRefreshed = Customer::find()->where(['status' => $row->status])->one();
        $this->assertNotEmpty($rowRefreshed);
        $this->assertEquals(7, $rowRefreshed->status);
    }

    public function testModify()
    {
        $searchName = 'name7';
        $newName = 'new name';

        $customer = Customer::find()
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => true]);
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals($newName, $customer->name);

        $customer = Customer::find()
            ->where(['name' => 'not existing name'])
            ->modify(['$set' => ['name' => $newName]], ['new' => false]);
        $this->assertNull($customer);
    }

    /**
     * @depends testInsert
     *
     * @see https://github.com/yiisoft/yii2/issues/6026
     */
    public function testInsertEmptyAttributes()
    {
        $record = new Customer();
        $record->save(false);

        $this->assertTrue($record->_id instanceof \MongoId);
        $this->assertFalse($record->isNewRecord);
    }
}
