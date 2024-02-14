<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Contract\CommandInterface;
use Robo\Robo;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Class for PdfTilerCommand Robo commands.
 */
class NewspapersPDFGenerationCommand extends OcrCommand {

    use DockerCleanupTrait;
    use QueuedParallelExecTrait;
    use RecursiveFileTreeTrait;

    /**
     * Generates PDFs for an entire tree.
     *
     * @param string $root
     *     The tree root to parse.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $extension
     *     The extensions to match when finding files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
     * @option $prefix
     *     The prefix to match when finding files.
     * @option $skip-confirm
     *     Should the confirmation process be skipped?
     * @option $skip-existing
     *     Should images with existing tiles be skipped?
     * @option $target-gid
     *     The gid to assign the target files.
     * @option $target-uid
     *     The uid to assign the target files.
     * @option $threads
     *     The number of threads the process should use.
     * @option $no-cleanup
     *     Do not clean up unused docker assets after running needed containers.
     *
     * @throws \Exception
     *
     * @command pdf:generate:tree
     */
    public function pdfFilesTree(
        string $root,
        array $options = [
            'extension' => 'jpg',
            'no-init' => FALSE,
            'prefix' => NULL,
            'skip-confirm' => FALSE,
            'skip-existing' => FALSE,
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'no-cleanup' => FALSE,
        ]
    ) : void {
        $regex_root = preg_quote($root, '/');
        $tmp_dir = $this->tmpDir . '/pdf';

        if (!empty($options['prefix'])) {
            $glob_path = "$root/{$options['prefix']}*.{$options['extension']}";
            $this->recursiveFiles = glob($glob_path);
        }
        else {
            $regex = "/^{$regex_root}\/[^\/]+\.{$options['extension']}\$/i";
            $this->recursiveFileTreeRoot = $root;
            $this->recursiveFileRegex = $regex;
            $this->setFilesToIterate();
            $this->getConfirmFiles('Generate PDF files', $options['skip-confirm']);
        }

        foreach ($this->recursiveFiles as $file_to_process) {
            $image_path_data = pathinfo($file_to_process);
            $embedded_path = str_replace($root, '', $image_path_data['dirname']);
            if (!file_exists("$tmp_dir/$embedded_path")) {
                mkdir("$tmp_dir/$embedded_path", 0777, TRUE);
            }

            copy($file_to_process, "$tmp_dir/$embedded_path/{$image_path_data['filename']}.{$image_path_data['extension']}");
        }
        $this->ocrTesseractTree(
            $tmp_dir,
            [
                'output_type' => 'pdf',
                'extension' => $options['extension'],
                'lang' => 'eng',
                'no-pull' => $options['no-init'],
                'no-unset-files' => FALSE,
                'oem' => 1,
                'skip-confirm' => TRUE,
                'skip-existing' => TRUE,
                'threads' => $options['threads'],
                'no-cleanup' => $options['no-cleanup'],
            ]
        );

        $regex_tmp = preg_quote($tmp_dir, '/');
        $regex = "/^{$regex_tmp}\/[^\/]+\.pdf\$/i";
        $this->recursiveFileTreeRoot = $tmp_dir;
        $this->recursiveFileRegex = $regex;
        $this->setFilesToIterate();

        foreach ($this->recursiveFiles as $file_to_process) {
            $pdf_path_data = pathinfo($file_to_process);
            $embedded_path = str_replace($tmp_dir, '', $pdf_path_data['dirname']);
            $full_path = "$root/$embedded_path/pdf";
            $final_file_name = str_replace('.jpg', '.pdf', $pdf_path_data['filename']);
            if (!file_exists($full_path)) {
                mkdir($full_path, 0755, TRUE);
            }
            copy($file_to_process, "$full_path/$final_file_name");
        }
    }

}
