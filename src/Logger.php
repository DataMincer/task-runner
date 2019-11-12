<?php

namespace TaskRunner;

class Logger implements LoggerInterface {

  private $debug;

  function __construct($debug = FALSE) {
    $this->debug = $debug;
  }

  public function err($msg) {
    fwrite(STDERR,"\e[1;31m" . $msg . "\e[0m\n");
  }

  public function warn($msg) {
    fwrite(STDERR,"\e[1;33m" . $msg . "\e[0m\n");
  }

  public function msg($msg) {
    fwrite(STDERR,"\e[1;37m" . $msg . "\e[0m\n");
  }

  public function debug($msg) {
    if ($this->debug) {
      fwrite(STDERR, "\e[1;30m" . $msg . "\e[0m\n");
    }
  }


}
