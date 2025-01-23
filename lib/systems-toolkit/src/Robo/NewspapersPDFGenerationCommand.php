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

    const MISSING_PDF_URL = 'https://newspapers.lib.unb.ca/serials_pages/with_missing_pdf';

    /**
     * Generates any missing PDFs.
     *
     * @param string $root
     *     The filesystem root.
     * @param string $pdf_root
     *     The root location for the PDF files.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $extension
     *     The extensions to match when finding files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
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
     * @option $limit
     *    The number of files to process.
     *
     * @throws \Exception
     *
     * @command newspapers.lib.unb.ca:generate-missing-pdf 
     */
    public function pdfFilesMissing(
        string $root,
        string $pdf_root,
        array $options = [
            'extension' => 'jpg',
            'no-init' => FALSE,
            'skip-existing' => FALSE,
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'no-cleanup' => FALSE,
            'limit' => 50,
        ]
    )
    {
        // Query the website for missing files using guzzle.
        $client = new \GuzzleHttp\Client();
        $limit = $options['limit'];
        $response = $client->request('GET', self::MISSING_PDF_URL . "/$limit");
        $missing_files = json_decode($response->getBody()->getContents(), TRUE);

        $file_data = [];
        foreach ($missing_files as $missing_file) {
            $file_data[] = [
                'file_path' => $root . '/' . $missing_file['rel_image_path'],
                'issue_id' => $missing_file['issue_id'],
                'title_id' => $missing_file['title_id'],
            ];
        }
        if (empty($file_data)) {
            exit("No missing files found.\n");
        }

        $options['skip-confirm'] = TRUE;
        $this->pdfFilesList(
            $pdf_root,
            $file_data,
            $options
        );
    }

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
        $files = glob("$root/$file_mask");
        if (empty($files)) {
            exit("No files found in $root.\n");
        }

        $file_data = [];
        foreach ($files as $file) {
            $file_data[] = [
                'file_path' => $file,
                'issue_id' => $issue_id,
                'title_id' => $title_id,
            ];
        }

        $this->pdfFilesList(
            $pdf_root,
            $file_data,
            $options
        );
    }

    /**
     * Generates PDFs for a a list of files.
     *
     * @param array $file_data
     *     An associative array of file data. Contains file_path, issue_id, and title_id.
     * @param string $pdf_root
     *     The root location for the PDF files.
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
    public function pdfFilesList(
        string $pdf_root,
        array $file_data,
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

        foreach ($file_data as $file) {
            $this->recursiveFiles[] = $file['file_path'];
        }

        if (empty($this->recursiveFiles)) {
            exit("No files passed.\n");
        }

        $this->getConfirmFiles('Generate PDF files', $options['skip-confirm']);
        foreach ($file_data as $file_datum) {
            $image_path_data = pathinfo($file_datum['file_path']);
            $embedded_path = "{$file_datum['title_id']}/{$file_datum['issue_id']}";
            if (!file_exists("$tmp_dir/$embedded_path")) {
                mkdir("$tmp_dir/$embedded_path", 0777, TRUE);
            }
            copy($file_datum['file_path'], "$tmp_dir/$embedded_path/{$image_path_data['filename']}.{$image_path_data['extension']}");
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

        $pdf_files = glob("$tmp_dir/*/*/*.pdf");
        foreach ($pdf_files as $pdf_file) {
            $pdf_path_data = pathinfo($pdf_file);
            $path_parts = explode('/', $pdf_path_data['dirname']);
            $issue_id = array_pop($path_parts);
            $title_id = array_pop($path_parts);

            $target_file_dir = "$pdf_root/$title_id/$issue_id";
            if (!file_exists($target_file_dir)) {
                mkdir("$target_file_dir", 0755, TRUE);
            }

            $target_filename = str_replace('.jpg', '.pdf', $pdf_path_data['filename']);
            $target_file_path = "$target_file_dir/$target_filename";
            $this->taskExecStack()
            ->stopOnFail()
            ->exec("sudo mv $pdf_file $target_file_path")
            ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} $target_file_path")
            ->run();
        }

        $this->taskExecStack()
        ->stopOnFail()
        ->exec("rm -rf $tmp_dir")
        ->run();
    }
}
