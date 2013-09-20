<?php
/**
 * @author Dmitry Groza <boxfrommars@gmail.com>
 */


$startTime = microtime(true);
$loader = require_once __DIR__ . '/../vendor/autoload.php';
/** @var \Book\BookApplication|\Doctrine\Common\Cache\Cache[]|\Symfony\Component\Form\FormFactory[]|Twig_Environment[]|\Doctrine\DBAL\Connection[]|\Book\BookService[]|\Symfony\Component\HttpFoundation\Session\Session[] $app */
$app = new \Book\BookApplication(array(
    'starttime' => $startTime,
    'debug' => true,
    'tmp_path' => '../tmp',
    'files_path' => realpath(__DIR__ . '/../public/files'),
    'is_cache' => false,
    'application_path' => realpath(__DIR__ . '/..'),
    'config' => array(
        'db' => array(
            'db.options' => array(
                'driver' => 'pdo_pgsql',
                'host' => 'localhost',
                'dbname' => 'mtsbook',
                'user' => 'mtsbook',
                'password' => 'mtsbook',
            ),
        ),
    ),
));

$app->register(new \Book\BookServiceProvider(), array());

$app->get('/', function() use ($app) {
    return $app['twig']->render('layout.twig', array(
        'content' => 'index page',
        'books' => $app['book.service']->fetchAll(),
    ));
});

$app->get('/book/{id}', function($id) use ($app) {
    return $app['twig']->render('layout.twig', array(
        'content' => 'index page',
        'books' => array($app['book.service']->fetch($id)),
    ));
});

$app->get('/admin', function() use ($app) {
    /** @var \Symfony\Component\HttpFoundation\Session\Flash\FlashBag $flashBag */
    $flashBag = $app['session']->getFlashBag();

    return $app['twig']->render('admin/layout.twig', array(
        'content' => $app['twig']->render('admin/books.twig', array('books' => $app['book.service']->fetchAll())),
        'flash' => $flashBag->all(),
    ));
});

$app->match('/admin/book/edit/{id}', function(\Symfony\Component\HttpFoundation\Request $request, $id) use ($app) {
    /** @var \Symfony\Component\HttpFoundation\Session\Flash\FlashBag $flashBag */
    $flashBag = $app['session']->getFlashBag();
    $book = $app['book.service']->fetch($id);

    if (!$book) return $app->redirect($app->url('admin_book_create'));

    /** @var \Symfony\Component\Form\FormBuilder $formBuilder */
    $formBuilder = $app['form.factory']->createBuilder(new \Book\BookForm(), $book);
    $form = $formBuilder->getForm();
    $form->handleRequest($request);

    if ($form->isValid()) {
        $app['book.service']->save($book);
        $flashBag->add('success', 'запись изменена');
        return $app->redirect($app->url('admin_book_edit', array('id' => $id)));
    }

    return $app['twig']->render('admin/layout.twig', array(
        'content' => $app['twig']->render('admin/book-form.twig', array('form' => $form->createView(), 'book' => $book)),
        'flash' => $flashBag->all(),
    ));
})->bind('admin_book_edit');

$app->match('/admin/book/create', function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    /** @var \Symfony\Component\HttpFoundation\Session\Flash\FlashBag $flashBag */
    $flashBag = $app['session']->getFlashBag();

    $book = new \Book\BookEntity();

    /** @var \Symfony\Component\Form\FormBuilder $formBuilder */
    $formBuilder = $app['form.factory']->createBuilder(new \Book\BookForm(), $book);
    $form = $formBuilder->getForm();
    $form->handleRequest($request);

    if ($form->isValid()) {
        $app['book.service']->save($book);
        $flashBag->add('success', 'запись изменена');
        return $app->redirect($app->url('admin_book_edit', array('id' => $book->getId())));
    }

    return $app['twig']->render('admin/layout.twig', array(
        'content' => $app['twig']->render('admin/book-form.twig', array('form' => $form->createView(), 'book' => $book)),
        'flash' => $flashBag->all(),
    ));
})->bind('admin_book_create');

$app->post('/upload/file', function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {

    /** @var \Symfony\Component\Form\FormBuilder $formBuilder */
    $formBuilder = $app['form.factory']->createBuilder(new \Book\BookFileForm(), array());
    $form = $formBuilder->getForm();
    $form->handleRequest($request);

    $response = array(
        'success' => false,
        'name' => null,
        'size' => null,
    );

    if ($form->isValid()) {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $form['x-files']->getData();

        $filename = 'file-' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($app['files_path'], $filename);
        $response = array(
            'success' => true,
            'name' => $filename,
            'size' => $file->getClientSize(),
        );

    } else {
        print_r($form->getErrors());
    }

    return $app->json($response);
});
$app->get('/login', function(\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    return $app['twig']->render('admin/login.twig', array(
        'error'         => $app['security.last_error']($request),
        'last_username' => $app['session']->get('_security.last_username'),
    ));
});


$app['logtime']('before run');
$app->run();
$app['logtime']("last codeline\n");