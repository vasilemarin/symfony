<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\FileField;
use Symfony\Component\HttpFoundation\File\File;

class FileFieldTest extends TestCase
{
    public static $tmpFiles = array();

    protected static $tmpDir;

    protected $field;

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'symfony-test';
    }

    protected function setUp()
    {
        parent::setUp();

        $this->field = $this->factory->getInstance('file', 'file');
    }

    protected function tearDown()
    {
        foreach (self::$tmpFiles as $key => $file) {
            @unlink($file);
            unset(self::$tmpFiles[$key]);
        }
    }

    public function createTmpFile($path)
    {
        self::$tmpFiles[] = $path;
        file_put_contents($path, 'foobar');
    }

    public function testSubmitUploadsNewFiles()
    {
        $tmpDir = self::$tmpDir;
        $generatedToken = '';

        $this->storage->expects($this->atLeastOnce())
            ->method('getTempDir')
            ->will($this->returnCallback(function ($token) use ($tmpDir, &$generatedToken) {
                // A 6-digit token is generated by FileUploader and passed
                // to getTempDir()
                $generatedToken = $token;

                return $tmpDir;
            }));

        $file = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
        $file->expects($this->once())
             ->method('move')
             ->with($this->equalTo($tmpDir));
        $file->expects($this->any())
             ->method('isValid')
             ->will($this->returnValue(true));
        $file->expects($this->any())
             ->method('getName')
             ->will($this->returnValue('original_name.jpg'));
        $file->expects($this->any())
             ->method('getPath')
             ->will($this->returnValue($tmpDir.'/original_name.jpg'));

        $this->field->submit(array(
            'file' => $file,
            'token' => '',
            'name' => '',
        ));

        $this->assertRegExp('/^\d{6}$/', $generatedToken);
        $this->assertEquals(array(
            'file' => $file,
            'token' => $generatedToken,
            'name' => 'original_name.jpg',
        ), $this->field->getDisplayedData());
        $this->assertEquals($tmpDir.'/original_name.jpg', $this->field->getData());
    }

    public function testSubmitKeepsUploadedFilesOnErrors()
    {
        $tmpDir = self::$tmpDir;
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . 'original_name.jpg';
        $this->createTmpFile($tmpPath);

        $this->storage->expects($this->atLeastOnce())
            ->method('getTempDir')
            ->with($this->equalTo('123456'))
            ->will($this->returnValue($tmpDir));

        $this->field->submit(array(
            'file' => null,
            'token' => '123456',
            'name' => 'original_name.jpg',
        ));

        $this->assertTrue(file_exists($tmpPath));

        $file = new File($tmpPath);

        $this->assertEquals(array(
            'file' => $file,
            'token' => '123456',
            'name' => 'original_name.jpg',
        ), $this->field->getDisplayedData());
        $this->assertEquals($tmpPath, $this->field->getData());
    }

    public function testSubmitEmpty()
    {
        $this->storage->expects($this->never())
            ->method('getTempDir');

        $this->field->submit(array(
            'file' => '',
            'token' => '',
            'name' => '',
        ));

        $this->assertEquals(array(
            'file' => '',
            'token' => '',
            'name' => '',
        ), $this->field->getDisplayedData());
        $this->assertEquals(null, $this->field->getData());
    }

    public function testSubmitEmptyKeepsExistingFiles()
    {
        $tmpPath = self::$tmpDir . DIRECTORY_SEPARATOR . 'original_name.jpg';
        $this->createTmpFile($tmpPath);
        $file = new File($tmpPath);

        $this->storage->expects($this->never())
            ->method('getTempDir');

        $this->field->setData($tmpPath);
        $this->field->submit(array(
            'file' => '',
            'token' => '',
            'name' => '',
        ));

        $this->assertEquals(array(
            'file' => $file,
            'token' => '',
            'name' => '',
        ), $this->field->getDisplayedData());
        $this->assertEquals($tmpPath, $this->field->getData());
    }
}