<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Contract\CommandInterface;
use Robo\Robo;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for PdfTilerCommand Robo commands.
 */
class NewspapersPDFGenerationCommand extends SystemsToolkitCommand {

    use DockerCleanupTrait;
    use QueuedParallelExecTrait;
    use RecursiveFileTreeTrait;

    /**
     * The docker image to use for Imagemagick commands.
     *
     * @var string
     */
    private string $imagemagickImage;

    /**
     * Gets the Imagemagick docker image from config.
     *
     * @throws \Exception
     *
     * @hook init
     */
    public function setImagingImage() : void {
        $this->imagemagickImage = Robo::Config()->get('syskit.imaging.imagemagickImage');
        if (empty($this->imagemagickImage)) {
            throw new \Exception(sprintf('The imagemagick docker image has not been set in the configuration file. (imagemagickImage)'));
        }
    }

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

        if (!$options['no-init']) {
            $this->setPullImagemagickImage();
        }
        $options['no-init'] = TRUE;

        if (!empty($options['prefix'])) {
            $glob_path = "$root/{$options['prefix']}*.{$options['extension']}";
            $this->recursiveFiles = glob($glob_path);
        }
        else {
            $regex = "/^{$regex_root}\/[^\/]+\.{$options['extension']}$/i";
            $this->recursiveFileTreeRoot = $root;
            $this->recursiveFileRegex = $regex;
            $this->setFilesToIterate();
            $this->getConfirmFiles('Generate PDF files', $options['skip-confirm']);
        }

        foreach ($this->recursiveFiles as $file_to_process) {
            $pdf_file_path_info = pathinfo($file_to_process);
            if ($options['skip-existing'] &&
                file_exists("{$pdf_file_path_info['dirname']}/pdf/{$pdf_file_path_info['filename']}.pdf")
            ) {
                $this->say("Skipping file with existing PDFs [$file_to_process]");
            }
            else {
                $this->setAddCommandToQueue($this->getPdfCreateCommand($file_to_process, $options));
            }
        }
        if (!empty($options['threads'])) {
            $this->setThreads($options['threads']);
        }
        $this->setRunProcessQueue('Generate PDF files');
        if (!$options['no-cleanup']) {
            $this->applicationCleanup();
        }
    }

    /**
     * Generates PDF Files for a list of files.
     *
     * @param string $filepath
     *     The file containing the file list.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $no-init
     *     Do not build and pull docker images prior to running.
     * @option $skip-confirm
     *     Should the confirmation process be skipped?
     * @option $skip-existing
     *     Should images with existing tiles be skipped?
     * @option $step
     *     The zoom step to use.
     * @option $target-gid
     *     The gid to assign the target files.
     * @option $target-uid
     *     The uid to assign the target files.
     * @option $threads
     *     The number of threads the process should use.
     * @option $tile-size
     *     The tile size to use.
     * @option $no-cleanup
     *     Do not clean up unused docker assets after running needed containers.
     *
     * @throws \Exception
     *
     * @command pdf:generate:from-list
     */
    public function pdfFiles(
        string $filepath,
        array $options = [
            'no-init' => FALSE,
            'skip-confirm' => FALSE,
            'skip-existing' => FALSE,
            'step' => '200',
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => NULL,
            'tile-size' => '256',
            'no-cleanup' => FALSE,
        ]
    ) : void {
        if (!$options['no-init']) {
            $this->setPullImagemagickImage();
        }
        $options['no-init'] = TRUE;

        $files_to_process = explode(
            "\n",
            file_get_contents($filepath)
        );

        foreach ($files_to_process as $file_to_process) {
            $file_to_process = trim($file_to_process);
            if (!empty($file_to_process)) {
                $pdf_file_path_info = pathinfo($file_to_process);
                if ($options['skip-existing'] &&
                    file_exists("{$pdf_file_path_info['dirname']}/pdf/{$pdf_file_path_info['filename']}.pdf")
                ) {
                    $this->say("Skipping file with existing tiles [$file_to_process]");
                }
                else {
                    $this->setAddCommandToQueue($this->getPdfCreateCommand($file_to_process, $options));
                }
            }
        }

        if (!empty($options['threads'])) {
            $this->setThreads($options['threads']);
        }
        $this->setRunProcessQueue('Generate PDF files');
        if (!$options['no-cleanup']) {
            $this->applicationCleanup();
        }
    }

    /**
     * Generates PDF Files for a specific NBNP issue.
     *
     * @param string $root
     *     The NBNP webtree root file location.
     * @param string $issue_id
     *     The issue entity ID to process.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $no-cleanup
     *     Do not clean up unused docker assets after running.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
     * @option $skip-existing
     *     Skip any issues with tiles that have been previously generated.
     * @option $threads
     *     The number of threads the process should use.
     *
     * @command newspapers.lib.unb.ca:issue:generate-pdf
     *
     * @throws \Exception
     */
    public function nbnpPdfIssue(
        string $root,
        string $issue_id,
        array $options = [
            'no-cleanup' => FALSE,
            'no-init' => FALSE,
            'skip-existing' => FALSE,
            'threads' => 1,
        ]
    ) : void {
        $cmd_options = [
            'extension' => 'jpg',
            'no-init' => $options['no-init'],
            'prefix' => "{$issue_id}-",
            'skip-confirm' => TRUE,
            'skip-existing' => $options['skip-existing'],
            'target-gid' => '102',
            'target-uid' => '100',
            'threads' => $options['threads'],
            'no-cleanup' => TRUE,
        ];
        $this->pdfFilesTree(
            $root . '/files/serials/pages',
            $cmd_options
        );
        if (!$options['no-cleanup']) {
            $this->applicationCleanup();
        }
    }

    /**
     * Builds the Robo command used to generate the PDF.
     *
     * @param string $file
     *     The file to parse.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $tile-size
     *     The tile size to use.
     * @option $step
     *     The zoom step to use.
     * @option $target-uid
     *     The uid to assign the target files.
     * @option $target-gid
     *     The gid to assign the target files.
     *
     * @return \Robo\Contract\CommandInterface
     *     The Robo command, ready to execute.
     */
    private function getPdfCreateCommand(
        string $file,
        array $options = [
            'target-gid' => '102',
            'target-uid' => '100',
        ]
    ) : CommandInterface {
        $pdf_file_path_info = pathinfo($file);
        $tmp_dir = "$this->tmpDir/{$pdf_file_path_info['filename']}";

        $hocr_filepath = "/tmp/{$pdf_file_path_info['filename']}.hocr";
        if (!file_exists($hocr_filepath)) {
            $this->say("No hocr file found for $file. Grabbing...");
            $hocr = file_get_contents("https://newspapers.lib.unb.ca/serials_pages/download/ocr/{$pdf_file_path_info['filename']}.jpg");
            file_put_contents($hocr_filepath, $hocr);
        }

        return $this->taskExecStack()
            ->stopOnFail()
            ->exec("sudo rm -rf $tmp_dir")
            ->exec("mkdir -p $tmp_dir")
            ->exec("cp $file $tmp_dir")
            ->exec("cp $hocr_filepath $tmp_dir")
            ->exec("docker run -i --rm -v $tmp_dir:/usr/src/app {$this->imagemagickImage} bash -c \"hocr2pdf -i /usr/src/app/{$pdf_file_path_info['filename']}.jpg -o /usr/src/app/{$pdf_file_path_info['filename']}.pdf < /usr/src/app/{$pdf_file_path_info['filename']}.hocr\"")
            ->exec("sudo cp $tmp_dir/{$pdf_file_path_info['filename']}.pdf {$pdf_file_path_info['dirname']}/pdf/")
            ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} {$pdf_file_path_info['dirname']}/pdf/{$pdf_file_path_info['filename']}.pdf");
            // ->exec("sudo rm -rf $tmp_dir");
    }

    /**
     * Generates a PDF of an image file.
     *
     * @param string $file
     *     The file to parse.
     * @param string[] $options
     *     The array of available CLI options.
     *
     * @option $tile-size
     *     The tile size to use.
     * @option $step
     *     The zoom step to use.
     * @option $target-uid
     *     The uid to assign the target files.
     * @option $target-gid
     *     The gid to assign the target files.
     * @option $no-init
     *     Do not build and pull docker images prior to running.
     *
     * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
     *
     * @command pdf:generate
     */
    public function generatePdfFiles(
        string $file,
        array $options = [
            'no-init' => FALSE,
            'skip-existing' => FALSE,
        ]
    ) : void {
        $pdf_file_path_info = pathinfo($file);
        if (!file_exists($file)) {
            throw new FileNotFoundException("File $file not Found!");
        }
        if (!$options['skip-existing'] ||
            !file_exists("{$pdf_file_path_info['dirname']}/pdf/{$pdf_file_path_info['filename']}.pdf")
        ) {
            if (!$options['no-init']) {
                $this->setPullImagemagickImage();
            }

            $command = $this->getPdfCreateCommand($file, $options);
            $command->run();
        }
    }

    /**
     * Pulls the docker image required to generate PDF files.
     *
     * @command pdf:pull-image
     */
    public function setPullImagemagickImage() : void {
        shell_exec("docker pull {$this->imagemagickImage}");
    }

}
