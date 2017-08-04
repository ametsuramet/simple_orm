# Simple ORM for LARAVEL
Laravel Package alternative for Eloquent ORM.

## Installation

Begin by pulling in the package through Composer.

```bash
composer require ametsuramet/simple_orm
```


Register the ServiceProvider in `config/app.php`

```php
'providers' => [
    // ...
    Amet\SimpleORM\ModelBuilderServiceProvider::class,
],
```



## Defining Models

The easiest way to create a model instance is using the simple_orm:model Artisan command:

```bash
php artisan simple_orm:model Project
```
### Command Options 

```bash
php artisan simple_orm:model Project --soft_delete=1 --methods=transactions,users --default_key=uuid --migration=1
```

All models will placed in app/ORM folder.

## Usage
in your controller file:

```php
// ...

use App\ORM\Project;

class yourController extends Controller {
	
	public function yourMethod()
	{
		$projects = new Project;
		$projects = $projects->get();

		dd($projects);
	}
// ...

```
or something like this:

```php
// ...
		$projects = (new Project)->get();
		dd($projects);
// ...

```

### Table Names
By Default SimpleORM will generate _$table_ attribut, but you can change name of table in Models

```php
...
class Project extends BaseQuery
{
	protected $table = 'projects';
...
```

### Default Keys
SimpleORM will also assume that each table has a primary key column named id. You may define a  _$default_key_ property to override.

```php
...
class Project extends BaseQuery
{
	protected $default_key = 'uuid';
...
```

### Public Method

#### where((array) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->where(['id',1])->get();
		dd($projects);
// ...

```

or 

```php
// ...
		$wheres = [
			['id','>=',10], 
			['date','>=','2017-01-01'], 
		];
		$projects = new Project;
		$projects = $projects->where($wheres)->get();
		dd($projects);
// ...

```
#### limit((int) $params, (int) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->limit(10)->get();
		dd($projects);
// ...

```

you can add offset :
```php
// ...
		$projects = new Project;
		$projects = $projects->limit(20,10)->get();
		dd($projects);
// ...

```

#### deleted_only()
Show deleted only
```php
// ...
		$projects = new Project;
		$projects = $projects->deleted_only()->get();
		dd($projects);
// ...

```
#### deleted((boolean) $params)
As noted above, soft deleted models will automatically be excluded from query results, you can use this method to include deleted records
```php
// ...
		$projects = new Project;
		$projects = $projects->deleted(true)->get();
		dd($projects);
// ...

```

#### info()
```php
// ...
		$projects = new Project;
		$projects = $projects->info();
		dd($projects);
// ...

```

#### orderBy((string) $params, (string) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->orderBy('id')->get();
		dd($projects);
// ...

```

or

```php
// ...
		$projects = new Project;
		$projects = $projects->orderBy('id','desc')->get();
		dd($projects);
// ...

```

#### groupBy()
```php
// ...
		$projects = new Project;
		$projects = $projects->groupBy('id')->get();
		dd($projects);
// ...

```

or


```php
// ...
		$projects = new Project;
		$projects = $projects->groupBy(['id','date'])->get();
		dd($projects);
// ...

```
#### insert((array) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->insert(["name" => 'new project']);
		dd($projects);
// ...

```

#### update($id,(array) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->update(29,["name" => 'new project']);
		dd($projects);
// ...

```
#### delete($id)
```php
// ...
		$projects = new Project;
		$projects = $projects->delete(29);
// ...

```

#### get()
```php
// ...
		$projects = new Project;
		$projects = $projects->get();
		dd($projects);
// ...

```

#### first()
```php
// ...
		$projects = new Project;
		$projects = $projects->first();
		dd($projects);
// ...

```

#### find($id)
```php
// ...
		$projects = new Project;
		$projects = $projects->find(29);
		dd($projects);
// ...

```

#### last()
```php
// ...
		$projects = new Project;
		$projects = $projects->last();
		dd($projects);
// ...

```

#### paginate((int) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->paginate($params);
		dd($projects);
// ...

```

#### set_show_column((array) $params)
```php
// ...
		$projects = new Project;
		$projects = $projects->set_show_column(['id','first_name','last_name'])->get();
		dd($projects);
// ...

```

#### getQueryLog()
```php
// ...
		$projects = new Project;
		$projects = $projects->getQueryLog();
		dd($projects);
// ...

```

### Relation

#### hasOne($relation_table,$relation_key,$table_key,$relation_name,$show_column(optional))
```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$this->hasOne('users','id','user_id','user');
	}
...
```
with show_column

```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$this->hasOne('users','id','user_id','user',['id','first_name','last_name']);
	}
...
```

#### hasMany($relation_table,$relation_key,$table_key,$relation_name,$show_column(optional))
```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$this->hasOne('users','id','user_id','user');
	}
...
```
with show_column

```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$this->hasOne('users','id','user_id','user',['id','first_name','last_name']);
	}
...
```

#### manyToMany($relation_table,$relation_key,$table_key,$relation_name,$pivot_table,$show_column(optional))
```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$pivot_table = ['user_projects','user_id','company_id'];
		$this->manyToMany('users','id','user_id','user',$pivot_table);
	}
...
```
with show_column

```php
...
class Project extends BaseQuery
{
	public function user()
	{
		$pivot_table = ['user_projects','user_id','company_id'];
		$this->manyToMany('users','id','user_id','user',$pivot_table,['id','first_name','last_name']);
	}
...
```
