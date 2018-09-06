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
#### cyberman
* ```cyberman:sendmessage```: Send a message via the CyberMan slack bot.

#### drupal
* ```drupal:8:rebasedevprod```: Rebase dev onto prod for multiple Drupal 8 Repositories.

#### github
* ```github:repo:cherry-pick-multiple```: Cherry pick a commit from a repo onto multiple others.
* ```github:repo:rebasedevprod```: Rebase dev onto prod for multiple GitHub Repositories.

#### k8s
* ```k8s:logs```: Get a kubernetes service logs from the URI and namespace.
* ```k8s:shell```: Get a kubernetes service shell from the URI and namespace.

#### ocr
* ```ocr:tesseract:file```: Generate OCR for a file.
* ```ocr:tesseract:tree```: Generate OCR for an entire tree.
* ```ocr:tesseract:tree:metrics```: Generate metrics for OCR confidence and word count for a tree.

### 3. Other Commands
Run ```vendor/bin/syskit``` to get a list of available commands.
