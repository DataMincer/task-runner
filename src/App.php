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

  public function __construct($params = []) {
    $this->setErrorHandler();
    $args = Docopt::handle($this->getUsageDefinition(), $params ? ['argv' => $params] : [])->args;
    // Configure our logger at the very beginning
    $this->logger = new Logger($this->getDebug($args));
    try {
      $this->options = $this->processArgs($args);
      $this->taskFactory = $this->createTaskFactory();
      $this->taskName = $this->getTaskNameFromParams($args);
    }
    catch (TaskRunnerException $e) {
      $this->logger->err($e->getMessage());
      die(1);
    }
  }

  /**
   * @param $params
   * @return mixed
   * @throws TaskRunnerException
   */
  protected function getTaskNameFromParams($params) {
    $tasks = array_filter($params, function($item) {
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
      $this->logger()->err($e->getMessage() . "\n" . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
      die(1);
    }
  }

  /**
   * @param $args
   * @return array
   * @throws TaskRunnerException
   */
  protected function processArgs($args) {
    $result = [];
    foreach (static::$paramsMap as $key => $arg) {
      if (!is_array($arg)) {
        if (!array_key_exists($arg, $args)) {
          throw New TaskRunnerException("Params error: arg/option not found '$arg'");
        }
        $result[$key] = $args[$arg];
      }
      else {
        $result[$key] = NULL;
        foreach ($arg as $sub_arg) {
          if (!array_key_exists($sub_arg, $args)) {
            throw New TaskRunnerException("Params error: sub-arg not found '$sub_arg'");
          }
          else {
            if ($args[$sub_arg]) {
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

  protected static function getDebug($args) {
    return isset($args['--debug']);
  }

  /**
   * Main function for defining Docopt grammar
   * @see: https://github.com/docopt/docopt.php
   * @return mixed
   */
  abstract protected function getUsageDefinition();

}
