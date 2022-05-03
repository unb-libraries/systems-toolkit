<?php

namespace UnbLibraries\SystemsToolkit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Helper\Table;

/**
 * Trait for running commands recursively in directories on a file tree.
 */
trait RecursiveDirectoryTreeTrait {

  /**
   * The regular expression used to match files within the tree.
   *
   * @var string
   */
  protected string $recursiveDirectoryFileRegex;

  /**
   * The directories to operate on.
   *
   * @var array
   */
  protected array $recursiveDirectories = [];

  /**
   * The tree root to parse recursively.
   *
   * @var string
   */
  protected string $recursiveDirectoryTreeRoot;

  /**
   * Sets up the files to iterate over.
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
   * Gets and confirm operation on files.
   *
   * @param string $operation_name
   *   The name of the operation to print.
   * @param bool $skip_confirm
   *   TRUE if the confirmations should be skipped. Defaults to FALSE.
   *
   * @throws \Exception
   */
  public function getConfirmDirs(
    string $operation_name = 'Operation',
    bool $skip_confirm = FALSE
  ) {
    if (!$skip_confirm) {
      $table = new Table($this->output());
      $table_rows = array_map([$this, 'arrayWrap'], $this->recursiveDirectories);
      $table->setHeaders([\Directory::class])->setRows($table_rows);
      $table->setStyle('borderless');
      $table->render();

      $continue = $this->syskitIo->confirm(sprintf('The %s will be applied to ALL of the above directories. Are you sure you want to continue?', $operation_name));
      if (!$continue) {
        throw new \Exception('Operation cancelled.');
      }
    }
  }

  /**
   * Maps items in an array element to a wrapper array.
   *
   * @param string $item
   *   The item to wrap in array.
   *
   * @return array
   *   An array with the item as a single element.
   */
  private function arrayWrap(string $item) : array {
    return [$item];
  }

}
