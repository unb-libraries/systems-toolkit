# systems-toolkit
## Automate all the common tasks.
A Robo based application that automates and standardizes several common tasks.

## Getting Started
### Requirements
The following packages are required to be globally installed:

* [PHP7](https://php.org/) - Install instructions [are here for OSX](https://gist.github.com/JacobSanford/52ad35b83bcde5c113072d5591eb89bd).
* [Composer](https://getcomposer.org/)
* [docker](https://www.docker.com)/[docker-compose](https://docs.docker.com/compose/)

### 1. Initial Setup
```
composer install --prefer-dist
```

### 2. Commands
```
 cyberman
  cyberman:sendmessage                            Sends a message via the CyberMan Slack bot.
 docker
  docker:cleanup                                  Removes stopped containers left behind by the toolkit.
 drupal
  drupal:actions-bulk-update                      Updates the GitHub Actions workflow file for all Drupal instances.
  drupal:doupdates                                Performs needed Drupal updates automatically.
  drupal:getupdates                               Gets the list of needed Drupal updates.
  drupal:readme-bulk-update                       Updates the README file for all Drupal instances.
  drupal:rebasedevprod                            Rebases dev onto prod for multiple Drupal Repositories. Robo Command.
  drupal:rebuild-redeploy                         Rebuilds all Drupal docker images and redeploy in their current state.
 dzi
  dzi:generate-tiles:tree                         Generates DZI tiles for an entire tree.
  dzi:pull-image                                  Pulls the docker image required to generate DZI tiles.
 github
  github:actions:restart-latest                   Restarts the latest builds for GitHub Actions deployed repositories.
  github:actions:status                           Shows the latest build results for GitHub Actions build repositories.
  github:dockworker:bulk-command                  Executes a command in dockworker repos and pushes any changes to GitHub.
  github:multiple-repo:script-modify              Perform, commit changes on multiple repositories with a shell script.
  github:repo:cherry-pick-multiple                Cherry-picks a commit from a repo onto multiple others.
  github:repo:rebasedevprod                       Rebases dev onto prod for multiple GitHub Repositories.
  github:user:activity                            Gets a list of recent commits to GitHub by a user.
 jira
  jira:boilerplate:multi-instance-worklist        Generates a JIRA issue description table for a multi-instance worklist.
  jira:multiple-repo:create-issue                 Creates a JIRA issue for multiple GitHub projects based on tags or name.
  jira:project:info                               Retrieves a project's critical information from the JIRA instance.
 k8s
  k8s:logs                                        Retrieves a k8s service's logs from the current running container.
  k8s:metadata:audit-repos                        Audits the kubernetes-metadata repo against lean repos.
  k8s:shell                                       Opens a shell within a k8s service's currently running container.
 newspapers.lib.unb.ca
  newspapers.lib.unb.ca:delete-year               Deletes an entire year of a title's issues.
  newspapers.lib.unb.ca:delete:issue              Deletes a newspapers.lib.unb.ca digital issue and associated assets.
  newspapers.lib.unb.ca:delete:issue-markers      Deletes newspapers.lib.unb.ca imported markers from a tree.
  newspapers.lib.unb.ca:generate-missing-dzi      Generates any missing DZIs.
  newspapers.lib.unb.ca:generate-missing-pdf      Generates any missing PDFs.
  newspapers.lib.unb.ca:generate-page-ocr         Generates and updates the OCR content for a digital serial page.
  newspapers.lib.unb.ca:get-page                  Downloads the image of a digital serial page.
  newspapers.lib.unb.ca:issue:generate-dzi        Generates DZI tiles for a specific NBNP issue.
  newspapers.lib.unb.ca:issue:generate-pdf        Generates PDFs for an issue.
  newspapers.lib.unb.ca:reset-missing-asset-scan  Makes newspapers.lib.unb.ca recheck every page image for an asset.
  newspapers.lib.unb.ca:stats                     Displays stats regarding newspapers.lib.unb.ca's digital content.
  newspapers.lib.unb.ca:title:generate-pdf        Generates PDFs for an title.
  newspapers.lib.unb.ca:title:generate-pdf:year   Generates PDFs for an title's year.
 ocr
  ocr:pull-image                                  Pulls the docker image required to generate OCR.
  ocr:tesseract:file                              Generates OCR for a file.
  ocr:tesseract:tree                              Generates OCR for an entire tree.
  ocr:tesseract:tree:metrics                      Generates metrics for OCR confidence and word count for a tree.
 pdf
  pdf:files-list                                  Generates PDFs for a a list of files.
  pdf:generate:tree                               Generates PDFs for an entire tree.
 repository-list
  repository-list:contains-file                   Lists all repositories that contain a specific file.
  repository-list:dockworker                      Lists all dockworker repositories.
  repository-list:drupal                          Lists all Drupal repositories.
```

### 3. Newspaper Import
Newspaper issue import and audit (formerly `newspapers.lib.unb.ca:create-issues-tree`, `:create-issue`, `:create-page` and `:audit-tree`) now live in the nbnp-ingest tool in the [newspapers.lib.unb.ca](https://github.com/unb-libraries/newspapers.lib.unb.ca) repository (`tools/nbnp-ingest`).

### 4. Other Commands
Run ```vendor/bin/syskit``` to get a list of available commands.
