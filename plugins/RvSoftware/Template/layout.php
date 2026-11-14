<?php
// Preserve the native layout, CSP, assets, hooks and all parameters. Only remove
// its viewport zoom restriction. Recheck this exact upstream marker on upgrades.
echo str_replace(
    'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no',
    'width=device-width, initial-scale=1.0',
    $this->render('kanboard:layout', $__template_args)
);
