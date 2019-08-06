<?php

namespace TaskRunner;

class Logger implements LoggerInterface {

  public function err($msg) {
    fwrite(STDERR,"\e[1;31m" . $msg . "\e[0m\n");
  }

  public function warn($msg) {
    fwrite(STDERR,"\e[1;33m" . $msg . "\e[0m\n");
  }

  public function msg($msg) {
    fwrite(STDERR,"\e[1;37m" . $msg . "\e[0m\n");
  }

  public function info($msg) {
    fwrite(STDERR,"\e[1;30m" . $msg . "\e[0m\n");
  }

  public function debug($msg) {
    fwrite(STDERR,"\e[1;30m" . $msg . "\e[0m\n");
  }


}
