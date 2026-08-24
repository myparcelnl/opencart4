# MyParcel for OpenCart 4

The official MyParcel extension for OpenCart 4. It connects an OpenCart store
to MyParcel for delivery options, shipment exports, labels and track & trace.

> The extension is currently in pre-release. The latest version in the
> extension manifest is `1.0.0-beta.5`.

## Requirements

- OpenCart 4.1.0.3 or newer
- PHP 8.2 or newer
- A MyParcel account and API key

## Features

- MyParcel delivery options in the checkout
- Capability-driven carriers, services and package types
- Manual shipment export from the order overview and order detail page
- Multiple independent shipments per order
- PDF labels and track & trace
- Product dimensions, weight and customs information
- Clear API validation messages in the OpenCart admin
- English, Dutch and Italian translations

## Installation

Download the `.ocmod.zip` package from the
[Releases page](https://github.com/myparcelnl/opencart4/releases).

In OpenCart:

1. Go to **Extensions > Installer** and upload the package.
2. Go to **Extensions > Extensions**, select **Modules** and install MyParcel.
3. On the same page, select **Shipping** and install MyParcel, then configure
   the shipping method (rate, geo zone and status).
4. Refresh the OpenCart modifications cache when prompted.
5. Open the MyParcel settings, enter the API key and save.
6. Click **Import carrier configuration** to load the carriers for your
   account.

## Development

Install the development dependencies:

```sh
composer install --working-dir=plugin/extension/myparcel
```

Run the unit tests:

```sh
plugin/extension/myparcel/vendor/bin/phpunit \
  --configuration plugin/extension/myparcel/phpunit.xml.dist
```

Build an installable package:

```sh
bin/build-release-zip
```

The package is written to `dist/myparcel.ocmod.zip`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.
Please report security issues privately as described in
[SECURITY.md](SECURITY.md).

## License

This project is licensed under the [MIT License](LICENSE.txt).
