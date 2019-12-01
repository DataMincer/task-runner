<?php

namespace TaskRunner;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Docopt;
use Exception;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

abstract class App {

  public static $name = 'default';

  /** @var TaskFactory */
  protected $taskFactory;
  protected $options;
  protected $taskName;
  protected static $paramsMap = [];
  /** @var LoggerInterface */
  protected $logger;
  /**
   * @var array
   */
  private $args;

  public function __construct($params = []) {
    $this->setErrorHandler();
    $this->args = Docopt::handle($this->getUsageDefinition(), $params ? ['argv' => $params] : [])->args;
    try {
      $this->logger = new Logger(static::$name);

      if ($this->isDebug()) {
        $debug_handler = new StreamHandler(STDERR, $this->isDebug() ? Logger::DEBUG : Logger::INFO);
        $debug_handler->setFormatter(new ColoredLineFormatter(new CliColorScheme(), "%message%\n"));
        $this->logger->pushHandler($debug_handler);
      }

      $message_handler = new StreamHandler(STDOUT, Logger::INFO, FALSE);
      $message_handler->setFormatter(new LineFormatter("%message%\n"));
      $this->logger->pushHandler($message_handler);

      $error_handler = new StreamHandler(STDERR, Logger::WARNING, FALSE);
      $error_handler->setFormatter(new ColoredLineFormatter(new CliColorScheme(), "%message%\n"));
      $this->logger->pushHandler($error_handler);
    } catch (Exception $e) {
      echo $e->getMessage();
      die(1);
    }
    $this->logger->pushProcessor(new PsrLogMessageProcessor());

    try {
      $this->options = $this->processArgs();
      $this->taskFactory = new TaskFactory($this, $this->options);
      $this->taskName = $this->getTaskName();
    }
    catch (TaskRunnerException $e) {
      $this->logger->error($e->getMessage());
      die(1);
    }
  }

  /**
   * @return mixed
   * @throws TaskRunnerException
   */
  protected function getTaskName() {
    $tasks = array_filter($this->options, function($item) {
      return is_bool($item) && $item;
    });
    $task = current(array_keys($tasks));

    if (!$task) {
      throw new TaskRunnerException('Task not provided');
    }

    if (!in_array($task, $this->taskFactory->getTasks())) {
      throw new TaskRunnerException("Task not implemented: $task");
    }

    return $task;
  }

  /**
   * Enables treating E_NOTICE as errors
   */
  protected function setErrorHandler() {
    // Catch all notices
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
      throw new TaskRunnerException("PHP Notice[$errno]: $errstr" . "\n$errfile:$errline");
    }, E_NOTICE | E_WARNING);
  }

  /**
   * @param TaskInterface $task
   */
  public function run($task = NULL) {
    try {
      $this->taskFactory->runTask(isset($task) ? $task : $this->taskName, $this->options);
    }
    catch(Exception $e) {
      if ($this->isDebug()) {
        $this->logger->error($e->getMessage() . "\n" . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
      }
      else {
        $this->logger->error($e->getMessage());
      }
      die(1);
    }
  }

  /**
   * @return array
   * @throws TaskRunnerException
   */
  protected function processArgs() {
    $result = [];
    foreach (static::$paramsMap as $key => $arg) {
      if (!is_array($arg)) {
        if ((is_string($arg) || is_numeric($arg)) && array_key_exists($arg, $this->args)) {
          $result[$key] = $this->args[$arg];
        }
        else {
          $result[$key] = $arg;
        }
      }
      else {
        $result[$key] = NULL;
        foreach ($arg as $sub_arg) {
          if (!array_key_exists($sub_arg, $this->args)) {
            throw New TaskRunnerException("Params error: sub-arg not found '$sub_arg'");
          }
          else {
            if ($this->args[$sub_arg]) {
              $result[$key] = $sub_arg;
            }
          }
        }
      }
    }
    return $result;
  }

  public function logger() {
    return $this->logger;
  }

  protected function isDebug() {
    return isset($this->args['--debug']) && $this->args['--debug'];
  }

  /**
   * Main function for defining Docopt grammar
   * @see: https://github.com/docopt/docopt.php
   * @return mixed
   */
  abstract protected function getUsageDefinition();

}
