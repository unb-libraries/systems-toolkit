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
 * ```github:repo:cherry-pick-multiple```: Cherry pick a commit from a repository into other instances.
 * ```github:repo:rebasedevprod```: Rebase dev onto prod for multiple GitHub Repositories.
 * ```drupal:8:rebasedevprod```: Rebase dev onto prod for multiple Drupal 8 Repositories.

### 3. Other Commands
Run ```vendor/bin/syskit``` to get a list of available commands.
