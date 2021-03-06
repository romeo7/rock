<?php

namespace rockunit;

use rock\Rock;
use rock\sanitize\Sanitize;
use rockunit\common\CommonTestTrait;

/**
 * @group base
 */
class CookieTest extends \PHPUnit_Framework_TestCase
{
    use CommonTestTrait;

    public function setUp()
    {
        static::sessionUp();
    }

    public function tearDown()
    {
        static::sessionDown();
    }

    // tests

    /**
     * @dataProvider providerGet
     */
    public function testGet($expected, $actual, $keys, $default = null, $filters = null)
    {
        $_COOKIE = $expected;
        $this->assertSame(Rock::$app->cookie->get($keys, $default, $filters), $actual);
    }

    public function providerGet()
    {
        return [
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                'text3',
                'title'
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                'param_1',
                'params.0'
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                'param_1',
                ['params', 0]
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                'param_1',
                ['params', '0']
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                null,
                ['params', '0', 1]
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']],
                'default',
                ['params', '0', 1],
                'default'
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']],
                'param_1',
                ['params', '0']
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']],
                'param_1',
                ['params', '0']
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']],
                ['param_1', 'param_2'],
                ['params']
            ],
            [
                ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']],
                '1',
                ['params', '0'],
                null,
                Sanitize::removeTags()->call('trim')->numbers()
            ],
        ];
    }

    public function testToArray()
    {
        $_COOKIE = ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']];
        $this->assertSame(
            Rock::$app->cookie->getAll(),
            ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']]
        );
        $this->assertSame(Rock::$app->cookie->getAll(['title', 'params'], ['params']), ['title' => 'text3']);
    }

    public function testGetIterator()
    {
        $_COOKIE = ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']];
        $this->assertSame(Rock::$app->cookie->getIterator([], ['title'])->current(), 1);
        $this->assertSame(
            Rock::$app->cookie->getIterator([], ['title'])->getArrayCopy(),
            ['id' => 1, 'params' => ['param_1', 'param_2']]
        );
    }

    public function testHasTrue()
    {
        $_COOKIE = ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']];
        $this->assertTrue(Rock::$app->cookie->exists('id'));
        $this->assertTrue(Rock::$app->cookie->exists('params.1'));
        $this->assertTrue(Rock::$app->cookie->exists(['params', 1]));
    }

    public function testHasFalse()
    {
        $_COOKIE = ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']];
        $this->assertFalse(Rock::$app->cookie->exists('test'));
        $this->assertFalse(Rock::$app->cookie->exists('params.77'));
        $this->assertFalse(Rock::$app->cookie->exists(['params', 77]));
    }

    public function testAdd()
    {
        Rock::$app->cookie->addMulti(['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']]);
        $this->assertSame(
            Rock::$app->cookie->getAll(),
            ['id' => 1, 'title' => 'text3', 'params' => ['param_1', 'param_2']]
        );
    }

    public function testRemove()
    {
        $_COOKIE = ['id' => 1, 'title' => 'text3', 'params' => ['  <b> param_1  </b> ', 'param_2']];
        Rock::$app->cookie->removeMulti(['id', 'params']);
        $this->assertSame(Rock::$app->cookie->getAll(), ['title' => 'text3']);
    }

    public function testSetFlash()
    {
        Rock::$app->cookie->setFlash('flash_1', 'text');
        $this->assertSame(Rock::$app->cookie->getFlash('flash_1'), 'text');
        $this->assertSame(
            Rock::$app->cookie->getAll(),
            array(
                'flash_1' => 'text',
                '__flash' =>
                    array(
                        'flash_1' => 0,
                    ),
            )
        );
    }


    public function testGetAllFlashes()
    {
        Rock::$app->cookie->setFlash('flash_1', 'text');
        Rock::$app->cookie->setFlash('flash_2');
        $this->assertSame(
            Rock::$app->cookie->getAllFlashes(),
            array(
                'flash_1' => 'text',
                'flash_2' => true,
            )
        );
        Rock::$app->cookie->removeFlash('flash_2');
        $this->assertSame(
            Rock::$app->cookie->getAllFlashes(),
            array(
                'flash_1' => 'text',
            )
        );
        Rock::$app->cookie->removeAllFlashes();
        $this->assertSame(Rock::$app->cookie->getAllFlashes(), []);
    }
}
 