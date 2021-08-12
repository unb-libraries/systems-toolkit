<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;

/**
 * Class for DrupalUpdatesCommand Robo commands.
 */
class Drupal7UpdatesCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Perform Drupal 7 updates.
   *
   * @param array $updates
   *   Modules to update. Should be in the form of module,oldver,newver.
   *
   * @throws \Exception
   *
   * @command drupal:7:doupdates
   */
  public function doDrupal7Updates(array $updates) {
    if(empty($updates)) {
      return $this->say('No updates requested!');
    }

    $this->say('Getting Drupal 7 repostories from GitHub');
    $this->setRepositoryList(
      [],
      ['drupal7'],
      [],
      []
    );

    $parsedUpdates = [];
    foreach($updates as $update) {
      $line = explode(',', $update);
      $find = "projects[{$line[0]}][version] = \"{$line[1]}\"";
      $replace = "projects[{$line[0]}][version] = \"{$line[2]}\"";
      $commit = "{$line[0]} {$line[1]} -> {$line[2]}";
      $parsedUpdates[preg_quote($find)] = ['replace' => $replace, 'commit' => $commit];
    }


    $branch = 'master';
    $committer = ['name' => $this->userName, 'email' => $this->userEmail];

    foreach($this->githubRepositories as $repository) {
      $owner = $repository['owner']['login'];
      $repo = $repository['name'];

      $this->say("Scanning {$repo}...");
      $file = 'make/' . preg_replace(['/build-profile-/', '/\./'], ['', '_'], $repo) . '.makefile';
      $oldContent = $this->client->api('repo')->contents()->download($owner, $repo, $file, $branch);

      foreach($parsedUpdates as $find => $info) {
        if(preg_match("/$find/", $oldContent)) {
          $newContent = preg_replace("/$find/", $info['replace'], $oldContent);
          $this->say($info['commit']);
          $oldHashes = $this->client->api('repo')->contents()->show($owner, $repo, $file, $branch);

          $this->client->api('repo')
            ->contents()
            ->update($owner, $repo, $file, $newContent, $info['commit'], $oldHashes['sha'], $branch, $committer);

          $oldContent = $newContent;
          if(count($parsedUpdates) > 1) {
            sleep(2);
          }
        }
      }
    }
  }
}
