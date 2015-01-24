### Основы

	<?php
	// web/index.php
	require_once __DIR__.'/../vendor/autoload.php';

	$app = new Silex\Application();

// put delete get post
	$app->get('/hello/{name}', function ($name) use ($app) {
		if (false) {
        	$app->abort(404, "Post $id does not exist.");
    	}
	    return 'Hello '.$app->escape($name);
	});
// любой или несколько
	$app->match('/blog', function () {});

	$app->match('/blog', function () {})
	->method('PUT|POST');
// прим.: Также можно определять контроллеры как методы классов или как сервисы.

// получить app или request со своим именем
	$app->get('/blog/{id}', function (Application $foo, Request $bar, $id) {});

// обработать переменную маршрута
	$app->get('/user/{id}', function ($id) {})
		->convert('id', function ($id) { return (int) $id; });

// получить какую-то сущность на основе переменной маршрута
	$callback = function ($post, Request $request) {
	    return new Post($request->attributes->get('slug'));
	};
	$app->get('/blog/{id}/{slug}', function (Post $post) {})
		->convert('post', $callback);

// обработать сервисом
	$app['converter.user'] = $app->share(function () {
	    return new UserConverter();
	});
	$app->get('/user/{user}', function (User $user) {
	})->convert('user', 'converter.user:convert')

// соответсвие параметра регулярке
	$app->get('/blog/{postId}/{commentId}', function ($postId, $commentId) {})
		->assert('postId', '\d+')
		->assert('commentId', '\d+');

// дефолтное значение параметра
	$app->get('/{pageName}', function ($pageName) {})
		->value('pageName', 'index');

// именование маршрутов
	$app->get('/', function () {})
		->bind('homepage');

// настройка для сразу всех контроллеров. Не распространяется на $app->mount контроллеры
	$app['controllers']
	    ->value('id', '1')
	    ->assert('id', '\d+')
	    ->requireHttps()
	    ->method('get')
	    ->convert('id', function () { /* ... */ })
	    ->before(function () { /* ... */ })

// добавить перехватчик исключений, (abort тоже исключение)
    $app->error(function (\Exception $e, $code) {
	    switch ($code) {
	        case 404:
	            $message = 'The requested page could not be found.';
	            break;
	        default:
	            $message = 'We are sorry, but something went terribly wrong.';
	    }
	    return new Response($message);
	});

// перехвачик конкретных исключений
    $app->error(function (\LogicException $e, $code) {});

// редирект
    return $app->redirect('/hello');
// или внутренний редирект(forwarding)
    $subRequest = Request::create('/hello', 'GET');
	// или $subRequest = Request::create($app['url_generator']->generate('mainPage'), 'GET'); 
    return $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

// вернуть JSON
    if (!$user) {
        $error = ['message' => 'The user was not found.'];
        return $app->json($error, 404);
    }
    return $app->json($user);

// streaming
    $stream = function () {
	    $fh = fopen('http://www.example.com/', 'rb');
	    while (!feof($fh)) {
		    echo fread($fh, 1024);
		    ob_flush();
		    flush();
	    }
	    fclose($fh);
	};
    return $app->stream($stream);

// отправить файл
    return $app->sendFile('/base/path/file.jpg');


// trait для создания кастомного app!
    class MyApplication extends Application
	{
	    use Application\TwigTrait;
	    use Application\SecurityTrait;
	    use Application\FormTrait;
	    use Application\UrlGeneratorTrait;
	    use Application\SwiftmailerTrait;
	    use Application\MonologTrait;
	    use Application\TranslationTrait;
	}

// Безопасность

	// htmlspecialchars()
	$app->escape();


### Жизненный цикл

	$app->before(function (Request $request, Application $app) {});
	$app->after(function (Request $request, Response $response) {});
	// уже после ответа клиенту:
	$app->finish(function (Request $request, Response $response) {});

	// приоритет
	$app->before(function (Request $request) {}, 32);
	$app->before(function (Request $request) {}, Application::EARLY_EVENT);
	$app->before(function (Request $request) {}, Application::LATE_EVENT);

	// редирект (ака firewall)
	$app->before(function (Request $request) {
	    if (...) {
	        return new RedirectResponse('/login');
	    }
	});

### Группировка маршрутов

	// blog.php
	$blog = $app['controllers_factory'];
	$blog->get('/', function () { return 'Blog home page'; });
	return $blog;

	// app.php
	$app->mount('/blog', include 'blog.php');


### Сервисы
### Провайдеры

