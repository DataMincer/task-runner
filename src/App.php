<?php

namespace TaskRunner;

use Docopt;
use Exception;
use ReflectionClass;
use ReflectionException;

class App {

  /** @var TaskFactory */
  protected $taskFactory;

  protected $options;
  protected $taskName;

  protected $appClassNs;

  /** @var App */
  private static $instance = NULL;

  protected static $paramsMap = [
    'opt1' => '--opt1',
    'opt2' => '--opt2',
    'params' => 'PARAMS',
  ];

  /** @var LoggerInterface */
  protected $logger;

  public function __construct($params = []) {
    $this->setErrorHandler();
    $this->logger = new Logger();
    $args = Docopt::handle($this->getUsageDefinition(), $params ? ['argv' => $params] : [])->args;
    $this->options = $this->processArgs($args);
    $this->taskFactory = $this->createTaskFactory();
    $this->taskName = $this->getTaskNameFromParams($args);
  }

  public static function runApp($params = []) {
    static::getApp($params)->run();
  }

  public static function getApp($params = []) {
    if (static::$instance === NULL) {
      static::$instance = new App($params);
    }
    return static::$instance;
  }

  protected function getTaskNameFromParams($params) {
    $tasks = array_filter($params, function($item) {
      return is_bool($item) && $item;
    });
    $task = current(array_keys($tasks));

    if (!$task) {
      $this->logger()->err('Task not provided');
      die(1);
    }

    if (!in_array($task, $this->taskFactory->getTasks())) {
      $this->logger()->err("Task not implemented: $task");
      die(1);
    }

    return $task;
  }

  protected function createTaskFactory() {
    // Discover and instantiate TaskFactory
    try {
      $this->appClassNs = (new ReflectionClass($this))->getNamespaceName();
      $class = class_exists($this->appClassNs . '\\' . 'TaskFactory') ? $this->appClassNs . '\\' . 'TaskFactory' : 'TaskRunner\\' . 'TaskFactory';
      return new $class($this, $this->options);
    }
    catch(ReflectionException $e) {
      $this->logger()->err('Tasks discover error: ' . $e->getMessage());
      die(1);
    }
  }

  protected function setErrorHandler() {
    // Catch all notices
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
      throw new TaskRunnerException("PHP Notice[$errno]: $errstr" . "\n$errfile:$errline");
    }, E_NOTICE);
  }

  public function run($task = NULL) {
    try {
      $this->taskFactory->runTask(isset($task) ? $task : $this->taskName, $this->options);
    }
    catch(Exception $e) {
      $this->logger()->err($e->getMessage() . "\n" . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
      die(1);
    }
  }

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

  public function _getAppClassNs() {
    return $this->appClassNs;
  }

  public function logger() {
    return $this->logger;
  }

  protected function getUsageDefinition() {
    return <<<DOC
Usage:
  my-app (cmd1 [PARAMS ...] [--opt1=OPT1] |
          cmd2 [PARAMS ...] [--opt2=OPT2])

Commands:
  cmd1                Command 1 description.
  cmd2                Command 2 description.

Options:
  --opt1=OPT1         Option 1 description. [Default: option1_value]
  --opt2=OPT2         Option 2 description. [Default: option2_value]

Arguments:
  cmd1 [PARAMS ...]   Execute command 1 with array argument PARAM.
  cmd2 [PARAMS ...]   Execute command 2 with array argument PARAM. 
DOC;
  }

}
