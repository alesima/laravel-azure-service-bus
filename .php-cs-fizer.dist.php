<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(['src', 'tests']) // Directories to include
    ->exclude('vendor');

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'], // Use short arrays
        'single_quote' => true, // Use single quotes where possible
        'no_unused_imports' => true, // Remove unused imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'], // Alphabetize imports
        'not_operator_with_space' => false, // Consistent NOT operator usage
        'phpdoc_align' => ['align' => 'left'], // Align PHPDoc comments
    ])
    ->setFinder($finder);
