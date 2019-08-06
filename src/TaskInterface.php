<?php

namespace TaskRunner;

interface TaskInterface {

  public static function taskId();

  public function run();

}
