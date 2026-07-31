# Contributing

Thanks for helping improve MyParcel for OpenCart.

For a substantial change, please open an issue first so the approach and scope
can be discussed. Keep pull requests focused and include tests for changed
behaviour.

## Development checks

Install the development dependencies:

```sh
composer install --working-dir=plugin/extension/myparcel
```

Before opening a pull request, run:

```sh
composer validate --working-dir=plugin/extension/myparcel --strict
plugin/extension/myparcel/vendor/bin/phpunit \
  --configuration plugin/extension/myparcel/phpunit.xml.dist
find plugin/extension/myparcel -name '*.php' \
  -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
find plugin/extension/myparcel -name '*.js' \
  -not -path '*/vendor/*' -print0 | xargs -0 -n1 node --check
```

The pull request workflow also verifies compatibility with OpenCart 4.1.0.3
and builds the installable package.

Use short, conventional commit messages such as `fix: handle missing weight`.

Security vulnerabilities must be reported privately according to
[SECURITY.md](SECURITY.md).
