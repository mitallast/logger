<?php

class LoggerAppenderStreamTest extends BaseLoggerTestCase
{
    protected $backupGlobals = true;
    private $logFile='/tmp/log.txt';

    protected function setUp(){
        if(is_file($this->logFile)) unlink($this->logFile);
        parent::setUp();
    }

    protected function tearDown(){
        if(is_file($this->logFile)) unlink($this->logFile);
        parent::tearDown();
    }

    public function testConstructIOException()
    {
        $this->setExpectedException('LoggerIOException');
        new LoggerAppenderStream('invalid://wefwef');
    }

    public function testNotUseLock()
    {
        $this->mockFunction('flock', '', 'throw new BadFunctionCallException();');
        $appender = new LoggerAppenderStream('php://stdout');
        $appender->setUseLock(false);
        $appender->write(Logger::INFO, '');
    }

    public function testNotUseLockShortMessage()
    {
        $this->mockFunction('flock', '', 'throw new BadFunctionCallException();');
        $appender = new LoggerAppenderStream('php://stdout');
        $appender->setUseLock(true);
        $appender->setUseLockShortMessage(false);
        $appender->write(Logger::INFO, '');
        $appender->write(Logger::INFO, str_pad('', 4096, '1'));
    }

    public function testUseLockShortMessage()
    {
        $GLOBALS['called']=false;
        $this->mockFunction('flock', '', '$GLOBALS["called"]=true; return true;');
        $appender = new LoggerAppenderStream('/tmp/log.txt');
        $appender->setUseLock(true);
        $appender->setUseLockShortMessage(false);
        $appender->write(Logger::INFO, str_pad('', 4097, '1'));
        $this->assertEquals(true, $GLOBALS['called']);
    }

    public function testFork(){

        $before=uniqid('before');
        $firstChild=uniqid('firstChild');
        $secondChild=uniqid('secondChild');
        $after=uniqid('after');

        $writer = new LoggerAppenderStream($this->logFile);
        $writer->write(1, $before);
        // first fork
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->markTestIncomplete('could not fork');
        } else if ($pid) {

        } else {
            $writer->write(1, $firstChild);
            die();
        }
        // second fork
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->markTestIncomplete('could not fork');
        } else if ($pid) {

        } else {
            $writer->write(1, $secondChild);
            die();
        }
        pcntl_waitpid($pid, $status);
        sleep(1);
        $writer->write(1, $after);

        $expected=$before.$firstChild.$secondChild.$after;
        $this->assertEquals($expected, file_get_contents($this->logFile));
    }
}
