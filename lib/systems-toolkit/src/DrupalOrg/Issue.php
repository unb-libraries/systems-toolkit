<?php

namespace JacobSanford\DrupalDevelopment\DrupalOrg;

/**
 * Base class for Drupal.org Issues.
 */
class Issue {

  const DRUPAL_ORG_COMMENT_API_ENDPOINT = 'https://www.drupal.org/api-d7/comment/%s.json';
  const DRUPAL_ORG_COMMENT_LIST_API_ENDPOINT = 'https://www.drupal.org/api-d7/comment.json?node=%s';
  const DRUPAL_ORG_FILE_API_ENDPOINT = 'https://www.drupal.org/api-d7/file/%s.json';
  const DRUPAL_ORG_NODE_API_ENDPOINT = 'https://www.drupal.org/api-d7/node/%s.json';

  /**
   * The issue NID on d.o.
   *
   * @var int
   */
  protected $id;

  /**
   * The issue data.
   *
   * @var obj
   */
  protected $data;

  /**
   * The files attached to the issue.
   *
   * @var array
   */
  protected $files;

  /**
   * The comments attached to the issue.
   *
   * @var array
   */
  protected $comments;

  /**
   * The project.
   *
   * @var array
   */
  protected $project;

  /**
   * The issue version.
   *
   * @var string
   */
  protected $issueVersion;

  /**
   * Constructor.
   */
  private function __construct() {

  }

  /**
   * Constructor.
   */
  private function setId($id) {
    $this->id = $id;
  }

  /**
   * Constructor.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Constructor.
   */
  private function setData() {
    $json = file_get_contents(
      sprintf(self::DRUPAL_ORG_NODE_API_ENDPOINT, $this->id)
    );
    $this->data = json_decode($json);
    $this->issueVersion = $this->data->field_issue_version;
  }

  private function setComments() {
    $json_data = file_get_contents(
      sprintf(self::DRUPAL_ORG_COMMENT_LIST_API_ENDPOINT, $this->id)
    );

    foreach (json_decode($json_data)->list as $index => $comment) {
      $this->comments[] = [
        'id' => $comment->cid,
        'data' => $comment,
        'files' => [],
      ];
    }
  }

  private function setFiles() {
    foreach ($this->data->field_issue_files as $file) {

      $file = $file->file;
      $json_data = file_get_contents(
        sprintf(self::DRUPAL_ORG_FILE_API_ENDPOINT, $file->id)
      );
      $file_obj = json_decode($json_data);

      // Add file to issue.
      $this->files[] = [
        'id' => $file->id,
        'data' => $file_obj,
      ];

      // Add file to the comment it originated from.
      foreach ($this->comments as $comment_index => $comment) {
        if ($file->cid == $comment['data']->cid) {
          $this->comments[$comment_index]['files'][] = $file_obj;
          break;
        }
      }

    }
  }

  public function getProject() {
    return $this->project['machine_name'];
  }

  public function getVersion() {
    return $this->issueVersion;
  }

  private function setProject() {
    $project_id = $this->data->field_project->id;

    $json = file_get_contents(
      sprintf(self::DRUPAL_ORG_NODE_API_ENDPOINT, $project_id)
    );
    $project_data = json_decode($json);
    $this->project = [
      'id' => $project_id,
      'machine_name' => $project_data->field_project_machine_name,
      'data' => $project_data,
    ];
  }

  /**
   * Factory for issue from nid.
   */
  public static function loadIssue($id) {
    $obj = new static();
    $obj->setId($id);
    $obj->setData();
    $obj->setProject();
    $obj->setComments();
    $obj->setFiles();
    return $obj;
  }

  public function getFiles() {
    $comment_files = [];
    foreach ($this->comments as $comment_index => $comment) {
      if (!empty($comment['files'])) {
        $files = [];
        $file_names = [];

        foreach ($comment['files'] as $file) {
          $files[] = $file;
          $file_names[] = $file->name;
        }
        $comment_files[] = [
          'comment_index' => $comment_index + 1,
          'author' => $comment['data']->name,
          'comment' => $comment['data'],
          'file' => $files,
          'file_names' => $file_names,
        ];
      }
    }
    return $comment_files;
  }

  public function getComments() {
    return $this->comments;
  }

}
