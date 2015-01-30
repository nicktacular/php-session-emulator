# PHP Session Emulator

This is a little tool that you can use to emulate how PHP uses a session handler. If, for example, you write your own [session handler](https://github.com/nicktacular/php-mongo-session) and wish to test it, this tool will help by emulating how PHP's `session_*()` functions work in a way that you can unit test your session handler.

## Requirements

If you're on PHP ≥ 5.3, then just Composer. If you're running PHP < 5.3, there are no other requirements.

## Installing

If you're using PHP ≥ 5.3, you can install using composer, you can install quite simply like so:

```
composer require nicktacular/php-session-emulator --dev
```

If you're using PHP < 5.3, you can simple clone this repo and include these in your test bootstrap:

```php
include '/path/to/src/n1/Session/Emulator.php';
include '/path/to/src/n1/Session/HttpCookie.php';
```

## How to use

Docs will be done soon.
