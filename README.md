Silverstripe GitHub Actions CI Recipe
=================
Silverstripe Recipe for Bootstrapping a Project using GitHub Actions as a CI, for details on included/expanded features see the [docs](docs/).

## Maintainer Contact
* Ed Chipman ([UndefinedOffset](https://github.com/UndefinedOffset))


## Requirements
* SilverStripe Framework 4.4+


## Installation
__Composer (recommended):__
```
composer require webbuilders-group/silverstripe-recipe-github-ci --dev
```


## Getting Started
This recipe contains support for tests run against [PHPUnit](https://github.com/sebastianbergmann/phpunit) and [Behat](https://github.com/Behat/Behat) as well as code style validation using [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) (using PSR-2 standards). Out of the box this CI expects all site code to be in the `app` folder. It also expects all PHPUnit tests to be in `app/tests/PHPUnit`, with all Behat tests to be in `app/tests/behat/features`.

If you do not need Behat tests you will need to remove the `behat` job in `.github/workflows/ci.yml`. If you want to disable [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) you need to remove the "Validate Code Style" step from the same file.


## Failed Tests Artifacts
Artifacts such as an error log from Silverstripe, or screenshots from the Behat tests will be added to the artifacts section on for the action's run on GitHub. For the PHPUnit tests it will be named `CI-<run number>-phpunit`, for Behat tests it will be named `CI-<run number>-behat` unless you change them.


## Testing Locally
Testing locally is still possible though it will of course work differently than the GitHub Actions runner will.

#### For PHPUnit
To run PHPUnit locally you can use the following to run all tests in the `app/tests/PHPUnit` folder. If you want to run a specific test in that folder simply include the full path, so for example `app/tests/PHPUnit` might become `app/tests/PHPUnit/SomeFolder/SomeTest.php`.

```bash
vendor/bin/PHPUnit app/tests/PHPUnit
```

#### For Behat
For Behat you either need to have to have your Silverstripe install in a webserver or use [silverstripe/serve](https://github.com/silverstripe/silverstripe-serve) (included). As well you need to install and have running [ChromeDriver](https://chromedriver.chromium.org/downloads) in a version that matches your local install of Chrome.

After all of that, you should be able to run the following to run all Behat tests in the `app/tests/behat/features` folder. If you want to run a specific test in that folder simply include the full path, so for example `app/tests/behat/features` becomes `app/tests/behat/features/some-folder/some-scenario.feature`.

```bash
vendor/bin/behat @app ./app/tests/behat/features/ --rerun
```
