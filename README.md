# DataTableTwo SSP for Laravel



[![Latest Version on Packagist](https://img.shields.io/packagist/v/syamsoul/laravel-datatable-two-ssp.svg?style=flat-square)](https://packagist.org/packages/syamsoul/laravel-datatable-two-ssp)



This package allows you to manage your DataTable from server-side in Laravel app (improved version of ([old SoulDoit DataTable SSP](https://github.com/syamsoul/laravel-datatable-ssp)).


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
***NOTE***: Please see [CHANGELOG](CHANGELOG.md) for more information about what has changed recently.

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

And then add the trait to your controller:
```php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use SoulDoit\DataTableTwo\SSP;

class UserListController extends Controller
{
    use SSP;
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
    use SSP;

    private function dtColumns()
    {
        return [
            ['label'=>'ID',                'db'=>'id'              ],
            ['label'=>'Email',           'db'=>'email'        ],
            ['label'=>'Username',   'db'=>'username' ],
            ['label'=>'Created At',  'db'=>'created_at'],
        ];
    }


    private function dtQuery($selected_columns)
    {
        return \App\Models\User::select($selected_columns);
    }
}
```

&nbsp;
&nbsp;
## Support me

Please support me and I will contribute more code.

Please [make a donation](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=syamsoulazrien.miat@gmail.com&lc=US&item_name=Support%20me%20and%20I%20will%20contribute%20more&no_note=0&cn=&curency_code=USD&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted).

&nbsp;
&nbsp;
## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
