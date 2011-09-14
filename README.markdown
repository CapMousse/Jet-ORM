Jet-ORM
================

A light PHP5.3 ORM

Feel free to contribute!
------------------------

* Fork
* Report bug
* Help in development

Licence
-------

Released under a [BSD license](http://en.wikipedia.org/wiki/BSD_licenses)

How To ?
========

First, you need to pass a config array to the ORMConnector wich represent the db type, db name, adresse, login and password:
```php
<?php
OrmConnector::$config = array(
    'type'  => 'mysql',
    'host'  => 'localhost',
    'base'  => 'jet',
    'log'   => 'root',
    'pass'  => 'root'
);
```

Then, you must create model extending the OrmWrapper named of your table name in camel case and the id column must have the table name inside. For exemple, the table `user_project` contain a `id_user_project` column and model is named `UserProject`.
```php
class UserProject extends OrmWrapper{
    //your methods here
}
```

Now, in or out of your model, your can do some request to your table !

Doc
===

## Read

###Get a single element

Any method chain can be end by a `findOne()` and will return a unique current class of the first element of the request or `false` if nothing is found.

To find a user with the `ORM` project :

`$project = $this->where('name', '=', 'ORM')->findOne();`

This equal to the next sql statement : 

`SELECT * FROM user_project WHERE name = ORM`

You can quickly find a element by id when you pass the id directly to `findOne`

`$project = $this->findOne(1);`

###Get multiple element

Any method chain can be end by a `findMany()` and will return an array of current class or `false` if nothing is found.

To find all element:

`$projects = $this->findMany();`

Where type is paid: 

`$projects = $this->where('type', '=', 'paid')->findMany();`

###Read result

To get data from result your just need to access to the property directly from the object:

`$this->type;`

##Filter

Each of the filter can be chained.

###Join

You can easily join two model with `join`. `join` ask the type of join (`LEFT`, `INNER`...) then the asked model to be join and an array or string of condition :

````php 
<?php
$user = new User();
$project = new Project();

$userProjects = $user->where("id_user", "=", 1)
                     ->join('LEFT', $project, 'user.id_user = project.id_user')
                     ->find_many();
```

````php 
<?php

$user = new User();
$project = new Project();

$userProjects = $user->where("id_user", "=", 1)
                     ->join('LEFT', $project, array(
                          ""    => 'user.id_user = project.user_id',
                          "AND" => 'user.id_project = project.project_id',
                          "OR"  => 'user.name != project.name'
                     ))
                     ->find_many();
```

###Where

Where take three arguments: the column to be filtered then the statement and the result.

`$projects = $this->where('type', '=', 'paid')->findMany();`

`$projects = $this->where('name', '!=', 'test')->findMany();`

###Limit

Set the limit of your request

`$projects = $this->limit(7)->findMany();`

###Offset

Set the offset of your request

`$projects = $this->offset(12)->findMany();`

###Distinct

Set the request DISTINCT

`$projects = $this->distinct()->findMany();`

##Create/Save/Delete

###Create

To create a new row, your just need to use the `create()` method:

`$project->create()`

You can add directly data to the new model by specify an array of data to `create`:

```php
<?php

$project->create(array(
    'name' => 'test',
    'user_id' => 1,
    'type' => 'unpaid'
));````

###Modify

To modify a model, your just need to edit the asked property:

'$project->name = "Not a test";`

###Save

To save the model, just use the `save()` method:

'$project->save();`

###Delete

To delete the model, use the `delete()` method:

'$project->delete();`

## Other

To change the id column name, you can use the `setIdName($name)` method.

To quickly get the current id, your can use `getId()`.

To acess to the query log, use `OrmWrapper::$log`