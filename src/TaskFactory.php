<?php

namespace TaskRunner;

use ReflectionClass;
use ReflectionException;

class TaskFactory {

  /** @var ReflectionClass[] $tasks */
  protected $tasks = [];

  protected $options = [];

  protected $app;

  /**
   * TaskFactory constructor.
   * @param App $app
   * @param $options
   * @throws TaskRunnerException
   */
  function __construct(App $app, $options) {
    $this->app = $app;
    $this->options = $options;
    $this->findTasks();
  }

  /**
   * @throws TaskRunnerException
   */
  protected function findTasks() {
    $class_names = get_declared_classes();
    foreach($class_names as $class_name) {
      try {
        $class = new ReflectionClass($class_name);
        if ($class->implementsInterface('TaskRunner\\TaskInterface') && !$class->isAbstract()) {
          $task_id = $class->getMethod('taskId')->invoke(NULL);
          if (!empty($task_id)) {
            $this->tasks[$task_id] = $class;
          }
        }
      }
      catch(ReflectionException $e) {
        throw new TaskRunnerException('Discover tasks error: ' . $e->getMessage());
      }
    }
  }

  /**
   * @param $task_id
   * @param array $options
   * @return mixed
   * @throws TaskRunnerException
   */
  public function runTask($task_id, $options = []) {
    if (!isset($this->tasks[$task_id])) {
      throw new TaskRunnerException("Task $task_id is not defined");
    }
    /** @var TaskInterface $task */
    $task = $this->tasks[$task_id]->newInstance($this->app, $options === [] ? $this->options : $options + $this->options);
    return $task->run();
  }

  /**
   * @param bool $ignore_version_check
   * @return array
   * @throws TaskRunnerException
   */
  public function getTasks($ignore_version_check = FALSE) {
    $tasks = [];
    foreach($this->tasks as $task => $task_class) {
      try {
        if (!$ignore_version_check || !$task_class->getMethod('requiresVersionCheck')->invoke(NULL)) {
          $tasks[] = $task;
        }
      }
      catch(ReflectionException $e) {
        throw new TaskRunnerException('Discover tasks error: ' . $e->getMessage());
      }
    }
    return $tasks;
  }

}
