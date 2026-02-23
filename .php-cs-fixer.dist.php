<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor'])
    ->notPath('src/Kernel.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PHP85Migration' => true,
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

