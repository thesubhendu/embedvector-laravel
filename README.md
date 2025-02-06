# Recommendation engine using Open AI embedding and PostgresSQL pgvector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thesubhendu/recommender.svg?style=flat-square)](https://packagist.org/packages/thesubhendu/recommender)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/thesubhendu/recommender/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/thesubhendu/recommender/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/thesubhendu/recommender/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/thesubhendu/recommender/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/thesubhendu/recommender.svg?style=flat-square)](https://packagist.org/packages/thesubhendu/recommender)

This package provides a recommendation engine using Open AI embedding and PostgresSQL pgvector. It uses the openai api to generate embeddings and stores them in the database. It then uses pgvector to search for similar embeddings.

> **⚠️ Notice:** This package is currently in development and not yet ready for production use. Please use at your own risk.

## Installation

```bash
composer require thesubhendu/embedvector-laravel
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="recommender-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="embed-vector-config"
```

This is the contents of the published config file:

```php
return [
    'openai_api_key' => env('OPENAI_API_KEY', ''),
];
```
Add your openai api key to the .env file as OPENAI_API_KEY

To get api key login or signup at https://platform.openai.com/api-keys and create a new api key

## Usage

Example: say you want to find AI matching jobs for the customer
### Step 0: Prepare your model
Implement EmbeddableContract to Eloquent model 
use EmbeddableTrait

```php
<?php

namespace App\Models;

use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Traits\EmbeddableTrait;

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

## Credits

- [Subhendu Bhatta](https://github.com/thesubhendu)
- [All Contributors](../../contributors)


