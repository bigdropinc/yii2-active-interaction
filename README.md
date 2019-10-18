Active Interaction
==================
Extension to encapsulate business logic 

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist bigdropinc/yii2-active-interaction "*"
```

or add

```
"bigdropinc/yii2-active-interaction": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Usage

```php
$post = (new CreatePost)([
    'user' => \Yii::$app->user->identity
])->run(\Yii::$app->request->post());
```