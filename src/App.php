<?php

namespace TaskRunner;

use Docopt;
use Exception;
use ReflectionClass;
use ReflectionException;

abstract class App {

  /** @var TaskFactory */
  protected $taskFactory;
  protected $options;
  protected $taskName;
  protected $appClassNs;
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
    // Configure our logger at the very beginning
    $this->logger = new Logger($this->isDebug());
    try {
      $this->options = $this->processArgs();
      $this->taskFactory = $this->createTaskFactory();
      $this->taskName = $this->getTaskName();
    }
    catch (TaskRunnerException $e) {
      $this->logger->err($e->getMessage());
      die(1);
    }
  }

  /**
   * @return mixed
   * @throws TaskRunnerException
   */
  protected function getTaskName() {
    $tasks = array_filter($this->args, function($item) {
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
   * Discover and instantiate TaskFactory
   * @return mixed
   * @throws TaskRunnerException
   */
  protected function createTaskFactory() {
    try {
      $this->appClassNs = (new ReflectionClass($this))->getNamespaceName();
      $class = class_exists($this->appClassNs . '\\' . 'TaskFactory') ? $this->appClassNs . '\\' . 'TaskFactory' : 'TaskRunner\\' . 'TaskFactory';
      return new $class($this, $this->options);
    }
    catch(ReflectionException $e) {
      throw new TaskRunnerException('Tasks discover error: ' . $e->getMessage());
    }
  }

  /**
   * Enables treating E_NOTICE as errors
   */
  protected function setErrorHandler() {
    // Catch all notices
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
      throw new TaskRunnerException("PHP Notice[$errno]: $errstr" . "\n$errfile:$errline");
    }, E_NOTICE);
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
        $this->logger()->err($e->getMessage() . "\n" . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
      }
      else {
        $this->logger()->err($e->getMessage());
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
        if (!array_key_exists($arg, $this->args)) {
          throw New TaskRunnerException("Params error: arg/option not found '$arg'");
        }
        $result[$key] = $this->args[$arg];
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

  public function taskFactory() {
    return $this->taskFactory;
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
