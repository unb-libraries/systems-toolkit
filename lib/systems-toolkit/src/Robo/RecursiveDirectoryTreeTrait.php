<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Helper\Table;

/**
 *  Trait for running commands recursively in directories on a file tree.
 */
trait RecursiveDirectoryTreeTrait {

  /**
   * The regular expression used to match files within the tree.
   *
   * @var string
   */
  protected $recursiveDirectoryFileRegex = NULL;

  /**
   * The directories to operate on.
   *
   * @var array
   */
  protected $recursiveDirectories = [];

  /**
   * The tree root to parse recursively.
   *
   * @var string
   */
  protected $recursiveDirectoryTreeRoot = NULL;

  /**
   * Set up the files to iterate over.
   *
   * @throws \Exception
   */
  public function setDirsToIterate() {
    if (!file_exists($this->recursiveDirectoryTreeRoot)) {
      throw new \Exception(sprintf('The directory [%s] does not exist.', $this->recursiveDirectoryTreeRoot));
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->recursiveDirectoryTreeRoot, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    $regex = new RegexIterator($iterator, $this->recursiveDirectoryFileRegex, \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $path => $dir) {
      $file_info = pathinfo($path);
      $real_dir = $file_info['dirname'];
      if (!in_array($real_dir, $this->recursiveDirectories)) {
        $this->recursiveDirectories[] = $real_dir;
      }
    }

    if (empty($this->recursiveDirectories)) {
      throw new \Exception(sprintf('There are no files matching the regex [%s] in [%s].', $this->recursiveDirectoryFileRegex, $this->recursiveDirectoryTreeRoot));
    }
  }

  /**
   * Get and confirm operation on files.
   *
   * @param string $operation_name
   *   The name of the operation to print.
   *
   * @throws \Exception
   */
  public function getConfirmDirs($operation_name = 'Operation', $skip_confirm = FALSE) {
    if (!$skip_confirm) {
      $table = new Table($this->output());
      $table_rows = array_map([$this, 'arrayWrap'], $this->recursiveDirectories);
      $table->setHeaders(array('Directory'))->setRows($table_rows);
      $table->setStyle('borderless');
      $table->render();

      $continue = $this->confirm(sprintf('The %s will be applied to ALL of the above directories. Are you sure you want to continue?', $operation_name));
      if (!$continue) {
        throw new \Exception(sprintf('Operation cancelled.'));
      }
    }
  }

  /**
   * Map items in an array element.
   *
   * @param string $item
   *   The item to wrap in array.
   *
   * @return array
   *   An array with the item as a single element.
   */
  private function arrayWrap($item) {
    return [$item];
  }

}
