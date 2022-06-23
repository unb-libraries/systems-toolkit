<?php

namespace UnbLibraries\SystemsToolkit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Helper\Table;

/**
 * Trait for running commands recursively on a file tree.
 */
trait RecursiveFileTreeTrait {

  /**
   * The regular expression used to match files within the tree.
   *
   * @var string
   */
  protected string $recursiveFileRegex;

  /**
   * The files to operate on.
   *
   * @var array
   */
  protected array $recursiveFiles = [];

  /**
   * The tree root to parse recursively.
   *
   * @var string
   */
  protected string $recursiveFileTreeRoot;

  /**
   * Sets up the files to iterate over.
   *
   * @throws \Exception
   */
  public function setFilesToIterate() {
    if (!file_exists($this->recursiveFileTreeRoot)) {
      throw new \Exception(sprintf('The directory [%s] does not exist.', $this->recursiveFileTreeRoot));
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->recursiveFileTreeRoot, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      // Ignore "Permission denied".
      RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    $regex = new RegexIterator($iterator, $this->recursiveFileRegex, \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $path => $dir) {
      $file_info = pathinfo($path);
      $filename = $file_info['filename'];
      if ($filename[0] != '.') {
        $this->recursiveFiles[] = $path;
      }
    }

    if (empty($this->recursiveFiles)) {
      throw new \Exception(sprintf('There are no files matching the regex [%s] in [%s].', $this->recursiveFileRegex, $this->recursiveFileTreeRoot));
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
  public function getConfirmFiles(
    string $operation_name = 'Operation',
    bool $skip_confirm = FALSE
  ) {
    if (!$skip_confirm) {
      $table = new Table($this->output());
      $table_rows = array_map([$this, 'arrayWrap'], $this->recursiveFiles);
      $table->setHeaders(['Filename'])->setRows($table_rows);
      $table->setStyle('borderless');
      $table->render();

      $continue = $this->confirm(sprintf('The %s will be applied to ALL of the above files. Are you sure you want to continue?', $operation_name));
      if (!$continue) {
        throw new \Exception('Operation cancelled.');
      }
    }
  }

  /**
   * Maps items in an array element.
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
