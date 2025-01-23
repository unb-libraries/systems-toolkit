<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;
use UnbLibraries\SystemsToolkit\Robo\NewspapersLibUnbCaDeleteCommand;

/**
 * Class for PdfTilerCommand Robo commands.
 */
class NewspapersPDFGenerationCommand extends OcrCommand {

    use DockerCleanupTrait;
    use QueuedParallelExecTrait;
    use RecursiveFileTreeTrait;

    /**
     * Generates PDFs for an title's year.
     *
     * @param string $title_id
     *    The parent digital title ID.
     * @param string $year
     *     The year to generate PDFs for.
     * @param string $root
     *     The tree root to parse.
     * @param string $pdf_root
     *     The root location for the PDF files.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $extension
     *     The extensions to match when finding files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
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
     * @command newspapers.lib.unb.ca:title:generate-pdf:year 73 1900 /path/to/files
     */
    public function pdfFilesTitleYear(
        string $title_id,
        string $year,
        string $root,
        string $pdf_root,
        array $options = [
            'extension' => 'jpg',
            'no-init' => FALSE,
            'skip-confirm' => FALSE,
            'skip-existing' => FALSE,
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'no-cleanup' => FALSE,
        ]
    )
    {
        $issue_ids = NewspapersLibUnbCaDeleteCommand::getTitleYearIssues($title_id, $year);
        foreach ($issue_ids as $issue_id) {
            $options['prefix'] = "$issue_id-";
            $this->pdfFilesTree($root, $pdf_root, $title_id, $issue_id, $options);
        }
    }

    /**
     * Generates PDFs for an title.
     *
     * @param string $title_id
     *    The parent digital title ID.
     * @param string $root
     *     The tree root to parse.
     * @param string $pdf_root
     *     The root location for the PDF files.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $extension
     *     The extensions to match when finding files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
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
     * @command newspapers.lib.unb.ca:title:generate-pdf 73 /path/to/files
     */
     public function pdfFilesTitle(
        string $title_id,
        string $root,
        string $pdf_root,
        array $options = [
            'extension' => 'jpg',
            'no-init' => FALSE,
            'skip-confirm' => FALSE,
            'skip-existing' => FALSE,
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'no-cleanup' => FALSE,
        ]
    )
    {
        $issue_ids = NewspapersLibUnbCaDeleteCommand::getTitleIssues($title_id);
        foreach ($issue_ids as $issue_id) {
            $this->pdfFilesIssue($root, $pdf_root, $title_id, $issue_id, $options);
        }
    }

    /**
     * Generates PDFs for an issue.
     *
     * @param string $root
     *     The tree root to parse.
     * @param string $pdf_root
     *     The root location for the PDF files.
     * @param string $title_id
     *    The parent digital title ID.
     * @param string $issue_id
     *    The parent digital issue ID.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $extension
     *     The extensions to match when finding files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
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
     * @command newspapers.lib.unb.ca:issue:generate-pdf
     */
    public function pdfFilesIssue(
        string $root,
        string $pdf_root,
        string $title_id,
        string $issue_id,
        array $options = [
            'extension' => 'jpg',
            'no-init' => FALSE,
            'skip-confirm' => FALSE,
            'skip-existing' => FALSE,
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'no-cleanup' => FALSE,
        ]
    )
    {
        $tree_path = "$root/$title_id/$issue_id";
        $this->pdfFilesTree($tree_path, $pdf_root, $title_id, $issue_id, $options);
    }

    /**
     * Generates PDFs for an entire tree.
     *
     * @param string $root
     *     The tree root to parse.
     * @param string $pdf_root
     *     The root location for the PDF files.
     * @param string $title_id
     *    The parent digital title ID.
     * @param string $issue_id
     *    The parent digital issue ID.
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
        string $pdf_root,
        string $title_id,
        string $issue_id,
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
        $tmp_dir = $this->tmpDir . '/pdf';
        if (!empty($options['prefix'])) {
            $file_mask = $options['prefix'] . '*.' . $options['extension'];
        }
        else {
            $file_mask = '*.' . $options['extension'];
        }

        $this->recursiveFiles = glob("$root/$file_mask");
        if (empty($this->recursiveFiles)) {
            exit("No files found in $root matching $file_mask.\n");
        }

        $this->getConfirmFiles('Generate PDF files', $options['skip-confirm']);
        foreach ($this->recursiveFiles as $file_to_process) {
            $image_path_data = pathinfo($file_to_process);
            $embedded_path = str_replace($root, '', $image_path_data['dirname']);
            if (!file_exists("$tmp_dir/$embedded_path")) {
                mkdir("$tmp_dir/$embedded_path", 0777, TRUE);
            }
            copy($file_to_process, "$tmp_dir/$embedded_path/{$image_path_data['filename']}.{$image_path_data['extension']}");
        }
        $this->recursiveFiles = [];

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

        $this->recursiveFiles = [];
        $this->recursiveFiles = glob("$tmp_dir/*.pdf");

        $target_file_dir = "$pdf_root/$title_id/$issue_id";
        if (!file_exists($target_file_dir)) {
            mkdir("$target_file_dir", 0755, TRUE);
        }

        foreach ($this->recursiveFiles as $file_to_process) {
            $pdf_path_data = pathinfo($file_to_process);
            $target_filename = str_replace('.jpg', '.pdf', $pdf_path_data['filename']);
            $target_file_path = "$target_file_dir/$target_filename";
            $this->taskExecStack()
            ->stopOnFail()
            ->exec("sudo mv $file_to_process $target_file_path")
            ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} $target_file_path")
            ->run();
        }

        $this->taskExecStack()
        ->stopOnFail()
        ->exec("rm -rf $tmp_dir")
        ->run();
    }

}
