<!DOCTYPE html>
<html lang="<?= $this->app->jsLang() ?>"<?php if ($this->app->isRtlLanguage()): ?> dir="rtl"<?php endif; ?>>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="robots" content="noindex,nofollow">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="referrer" content="no-referrer">

        <?= $this->asset->colorCss() ?>
        <?= $this->asset->css('assets/css/vendor.min.css') ?>
        <?php if (! isset($not_editable)): ?>
            <?= $this->asset->css('assets/css/'.$this->user->getTheme().'.min.css') ?>
        <?php else: ?>
            <?= $this->asset->css('assets/css/light.min.css') ?>
        <?php endif ?>
        <?= $this->asset->css('assets/css/print.min.css', true, 'print') ?>
        <?= $this->asset->customCss() ?>

        <?php if (! isset($not_editable)): ?>
            <?= $this->asset->js('assets/js/vendor.min.js') ?>
            <?= $this->asset->js('assets/js/app.min.js') ?>
        <?php endif ?>

        <?= $this->hook->asset('css', 'template:layout:css') ?>
        <?= $this->hook->asset('js', 'template:layout:js') ?>

        <link rel="icon" type="image/png" href="<?= $this->url->dir() ?>assets/img/favicon.png?v=projecta-1">
        <link rel="apple-touch-icon" href="<?= $this->url->dir() ?>assets/img/favicon.png?v=projecta-1">
        <meta name="application-name" content="Projecta">
        <title><?= $this->text->e(isset($page_title) ? $page_title : (isset($title) ? $title : 'Inicio')) ?> · Projecta</title>

        <?= $this->hook->render('template:layout:head') ?>
    </head>
    <body class="rv-projecta" data-status-url="<?= $this->url->href('UserAjaxController', 'status') ?>"
          data-login-url="<?= $this->url->href('AuthController', 'login') ?>"
          data-keyboard-shortcut-url="<?= $this->url->href('DocumentationController', 'shortcuts') ?>"
          data-timezone="<?= $this->app->getTimezone() ?>"
          data-js-date-format="<?= $this->app->getJsDateFormat() ?>"
          data-js-time-format="<?= $this->app->getJsTimeFormat() ?>"
    >

    <?php if (isset($no_layout) && $no_layout): ?>
        <?= $this->app->flashMessage() ?>
        <?= $content_for_layout ?>
    <?php else: ?>
        <?= $this->hook->render('template:layout:top') ?>
        <?= $this->render('header', array(
            'title' => $title,
            'description' => isset($description) ? $description : '',
            'board_selector' => isset($board_selector) ? $board_selector : array(),
            'project' => isset($project) ? $project : array(),
        )) ?>
        <section class="page">
            <?= $this->app->flashMessage() ?>
            <?= $content_for_layout ?>
        </section>
        <?= $this->hook->render('template:layout:bottom') ?>
    <?php endif ?>
    </body>
</html>
