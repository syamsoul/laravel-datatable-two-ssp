# DataTableTwo SSP for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoul/laravel-datatable-two-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoul/laravel-datatable-two-ssp)


## Documentation, Installation and Usage Instructions

See the [documentation](https://info.souldoit.com/projects/laravel-datatable-two-ssp) for detailed installation and usage instructions.


&nbsp;
&nbsp;
## Introduction

This package allows you to manage your DataTable from server-side in Laravel app (improved version of [old SoulDoit DataTable SSP](https://github.com/syamsoul/laravel-datatable-ssp)).


&nbsp;
* [Requirement](#requirement)
* [Installation](#installation)
* [Usage & Reference](#usage--reference)
* [How to use it?](#how-to-use-it)
* [Example](#example)


&nbsp;
&nbsp;
## Requirement

* Laravel 8.x (and above)


&nbsp;
&nbsp;
## Installation


This package can be used in Laravel 8.x or higher. If you are using an older version of Laravel, there's might be some problem. If there's any problem, you can [create new issue](https://github.com/syamsoul/laravel-datatable-two-ssp/issues) and I will fix it as soon as possible.

You can install the package via composer:

``` bash
composer require syamsoul/laravel-datatable-two-ssp
```

&nbsp;

And you can publish the `config/sd-datatable-two-ssp.php` file:

``` bash
php artisan vendor:publish --provider="SoulDoit\DataTableTwo\DataTableServiceProvider"
```

&nbsp;
&nbsp;
## Usage & Reference

\* Before you read this section, you can take a look [the code example](#example) to make it more clear to understand.

&nbsp;
### How to use it?

First, you must add this line to your Controller:
```php
use SoulDoit\DataTableTwo\SSP;
```
&nbsp;

And then inject the SSP service to your controller:
```php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use SoulDoit\DataTableTwo\SSP;

class UserListController extends Controller
{
    public function index(SSP $dt)
    {
        // do something here
    }
```

&nbsp;
&nbsp;

Or, just simply run artisan command to create a DataTable file
``` bash
php artisan make:datatable UsersDataTable -e
```

&nbsp;
&nbsp;
## Example

```php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use SoulDoit\DataTableTwo\SSP;

class UserListController extends Controller
{
    public function index(SSP $dt)
    {
        $dt->setColumns([
            ['label'=>'ID',         'db'=>'id'          ],
            ['label'=>'Email',      'db'=>'email'       ],
            ['label'=>'Username',   'db'=>'username'    ],
            ['label'=>'Fullname',   'db_fake'=>'fullname', 'formatter'=>function($model){
                return $model->first_name . " " . $model->last_name;
            }],
            ['label'=>'Created At', 'db'=>'created_at', 'formatter'=>function($value, $model){
                return $value->format("Y-m-d H:i:s");
            }],
            ['db'=>'first_name', 'is_show'=>false],
            ['db'=>'last_name', 'is_show'=>false],
        ]);

        $dt->setQuery(function ($selected_columns) {
            return \App\Models\User::select($selected_columns);
        });

        // return JSON response
        return $dt->getData();

        // or return CSV file stream response
        // return $dt->getCsvFile();
    }
}
```

&nbsp;
&nbsp;
## Support me

If you find this package helps you, kindly support me by donating some BNB (BSC) to the address below.

```
0x364d8eA5E7a4ce97e89f7b2cb7198d6d5DFe0aCe
```

<img src="https://info.souldoit.com/img/wallet-address-bnb-bsc.png" width="150">


&nbsp;
&nbsp;
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
