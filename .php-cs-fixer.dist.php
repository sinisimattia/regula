<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->ignoreVCSIgnored(true)
;


// You can customize the rules using this reference: https://mlocati.github.io/php-cs-fixer-configurator
return (new PhpCsFixer\Config())
    ->setLineEnding("\n")
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'single_line_empty_body' => true,
        'no_unused_imports' => true,
        'type_declaration_spaces' => true,
        'no_whitespace_in_blank_line' => true,
        'no_extra_blank_lines' => true,
        'single_quote' => true,
        'array_indentation' => true,
        'get_class_to_class_keyword' => true,
        // PHP-10: drop @param/@return tags that only restate the signature, and docblocks left empty.
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'no_empty_phpdoc' => true,
        // PHP-25: @throws is the last tag in a docblock.
        'phpdoc_order' => ['order' => ['param', 'return', 'throws']],
    ]);
