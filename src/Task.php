<?php

namespace TaskRunner;

use Psr\Log\LoggerInterface;

abstract class Task implements TaskInterface {

  protected static $taskId;
  protected static $versionCheck = TRUE;

  /** @var LoggerInterface */
  protected $logger;

  protected $options;
  /* @var App */
  protected $app;

  public static function taskId() {
    return static::$taskId;
  }

  /** @noinspection PhpUnused */
  public static function requiresVersionCheck() {
    return static::$versionCheck;
  }

  /** @noinspection PhpUnused */
  public function __construct(App $app, $options) {
    $this->app = $app;
    $this->options = $options;
    $this->logger = $app->logger();
  }

}
