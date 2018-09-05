<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Console\Helper\Table;

/**
 *  Trait for running commands recursively on a file tree.
 */
trait RecursiveFileTreeTrait {

  /**
   * The regular expression used to match files within the tree.
   *
   * @var string
   */
  protected $fileRegex = NULL;

  /**
   * The files to operate on.
   *
   * @var array
   */
  protected $files = [];

  /**
   * The tree root to parse recursively.
   *
   * @var string
   */
  protected $treeRoot = NULL;

  /**
   * Set up the files to iterate over.
   *
   * @throws \Exception
   */
  public function setFilesToIterate() {
    if (!file_exists($this->treeRoot)) {
      throw new \Exception(sprintf('The directory [%s] does not exist.', $this->treeRoot));
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->treeRoot, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST,
      // Ignore "Permission denied".
      RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    $regex = new RegexIterator($iterator, $this->fileRegex, \RecursiveRegexIterator::GET_MATCH);
    foreach ($regex as $path => $dir) {
      $this->files[] = $path;
    }

    if (empty($this->files)) {
      throw new \Exception(sprintf('There are no files matching the regex [%s] in [%s].', $this->fileRegex, $this->treeRoot));
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
  public function getConfirmFiles($operation_name = 'Operation') {
    $table = new Table($this->output());
    $table_rows = array_map([$this, 'arrayWrap'], $this->files);
    $table->setHeaders(array('Filename'))
      ->setRows($table_rows);
    $table->setStyle('borderless');
    $table->render();

    $continue = $this->confirm(sprintf('The %s will be applied to ALL of the above files. Are you sure you want to continue?', $operation_name));
    if (!$continue) {
      throw new \Exception(sprintf('Operation cancelled.'));
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
