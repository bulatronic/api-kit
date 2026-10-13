<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    // config/ is dev-sandbox scaffolding (already .gitattributes export-ignore'd from the
    // dist archive, see AGENTS.md): bundles.php/reference.php/services.yaml/etc. are
    // (re)written by Symfony Flex recipes and `cache:clear`/`config:dump-reference`
    // whenever symfony.lock is stale or absent — e.g. on a pristine CI checkout, since
    // symfony.lock is itself .gitignore'd. Their exact formatting (e.g. whether Flex's
    // template happens to emit declare(strict_types=1)) isn't this project's code style to
    // own, and churns with every framework-bundle/recipe bump.
    ->exclude(['var', 'vendor', 'config'])
    ->notPath('src/Kernel.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_summary' => false,
        'phpdoc_separation' => false,
        'single_line_throw' => false,
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
        'native_function_invocation' => ['include' => ['@all']],
        'modernize_types_casting' => true,
        'get_class_to_class_keyword' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);

