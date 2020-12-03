# DynamoDb

[![Latest Stable Version](https://poser.pugx.org/matteomeloni/dynamodb/v/stable)](//packagist.org/packages/matteomeloni/dynamodb) 
[![Total Downloads](https://poser.pugx.org/matteomeloni/dynamodb/downloads)](//packagist.org/packages/matteomeloni/dynamodb)
[![Latest Unstable Version](https://poser.pugx.org/matteomeloni/dynamodb/v/unstable)](//packagist.org/packages/matteomeloni/dynamodb)
[![License](https://poser.pugx.org/matteomeloni/dynamodb/license)](//packagist.org/packages/matteomeloni/dynamodb)

This is where your description should go. Take a look at [contributing.md](contributing.md) to see a to do list.

## Installation

Via Composer

``` bash
$ composer require matteomeloni/dynamodb
```

## Usage

```php
use MatteoMeloni\DynamoDb\Eloquent\Model;

class User extends Model 
{
    protected $table = 'user';
}
```
---

####Retrive All Models
```php
$users = App\User::all();

foreach ($users as $user) {
    echo $user->attribute;
}

// Adding Additional Constraints
$users = new User();
$users = $users->where('field', 'operator', 'value')->get();
```

####Retrieving Single Models
```php
$user = new App\User();

// Retrieve a model by its primary key
$user = $user->find($id);

// Not Found Exceptions
$user = $user->findOrFail($id);

// Retrieve the first model matching the query constraints...
$user = $user->where('field', 'operator', 'value')->first();

// Not Found Exceptions
$user = $user->where('field', 'operator', 'value')->firstOrFail();
```
 
**Insert**
```php
$user = new Model;

$user->name = 'Mario';
$user->surname = 'Rossi';

$user->save();
```

**Update**
```php
$user = new User;

$user = $user->find($id);

$user->name = 'Marco';

$user->save();
```

**FirstOrCreate**
```php
// Retrieve User by email, or create it with the email and name attributes...
$user = User::firstOrCreate(
    ['email' => 'mariorossi@mail.com'], ['name' => 'Mario','surname' => 'Rossi']
);
```

**Deleting**
```php
// Deleting An Existing Model By Key
$user = new App\User()
$user = $user->find($id)->delete();

// Deleting Models By Query
$users = new App\User()
$users = $user->where('disabled', '=', true)->get();
$users->delete();
```

---
###Soft Deleting
To enable soft deletes for a model, use the Mmrp\Dynamodb\Traits\SoftDeletes trait on the model
```php
use MatteoMeloni\DynamoDb\Eloquent\Model;
use MatteoMeloni\DynamoDb\Eloquent\Traits\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
    
    protected $table = 'user';
}
```

**Including Soft Deleted Models**
```php
$users = new App\User();
$users = $users->withTrashed()
    ->where('account_id', 1)
    ->get();
```

**Retrieving Only Soft Deleted Models**
```php
$users = new App\User();
$users = $users->onlyTrashed()
    ->where('account_id', 1)
    ->get();
```

**Restoring Soft Deleted Models**
```php
$users = new User();
$users = $users->onlyTrashed()
    ->where('id', '=', $id)
    ->get()->restore();
```

**Permanently Deleting Models**
```php
$users = new User();
$users = $users->onlyTrashed()
    ->where('id', '=', $id)
    ->get()->forceDelete();
```

## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email author email instead of using the issue tracker.

## Credits

- [Matteo Meloni][link-author]
- [All Contributors][link-contributors]

## License

license. Please see the [license file](license.md) for more information.

[link-author]: https://github.com/matteomeloni
[link-contributors]: ../../contributors
