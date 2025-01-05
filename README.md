# Recommendation engine using Open AI embedding and PostgresSQL pgvector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thesubhendu/recommender.svg?style=flat-square)](https://packagist.org/packages/thesubhendu/recommender)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/thesubhendu/recommender/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/thesubhendu/recommender/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/thesubhendu/recommender/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/thesubhendu/recommender/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/thesubhendu/recommender.svg?style=flat-square)](https://packagist.org/packages/thesubhendu/recommender)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/recommender.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/recommender)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require thesubhendu/recommender
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="recommender-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="recommender-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="recommender-views"
```

## Usage

Example: say you want to find AI matching jobs for the customer
### Step 0: Prepare your model
Implement EmbeddableContract to Eloquent model 
use EmbeddableTrait

```php
<?php

namespace App\Models;

use Subhendu\Recommender\Contracts\EmbeddableContract;
use Subhendu\Recommender\Traits\EmbeddableTrait;

class Customer extends Model implements EmbeddableContract
{
    use EmbeddableTrait;
}
```

Once model is ready run

### Step 1:
`php artisan embedding:batch {modelName} {--type=sync|init} {--force}`

Example: `php artisan embedding:batch App\\Models\\Customer --type=init`

This will generate a jsonl file in `storage/app/embeddings/` which is uploaded to openai api

### Step 2:
`php artisan process-completed-batch`

This will process the embeddings  (generated in step 1) and store them in the database

- Repeat step 1 and 2 with other models if needed

### Step 3 :You can now search using the `matchingResults` method:

```php
$customer = Customer::find(1);
$customer->matchingResults(Job::class);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Subhendu Bhatta](https://github.com/thesubhendu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
