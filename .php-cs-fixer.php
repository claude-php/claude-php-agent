<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('vendor')
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP80Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
        'declare_strict_types' => true,
        'no_leading_import_slash' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_whitespace_in_blank_line' => true,
        'return_type_declaration' => true,
        'modifier_keywords' => true,
        'concat_space' => ['spacing' => 'one'],
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_multiline_whitespace_around_double_arrow' => true,
        'multiline_whitespace_before_semicolons' => true,
        'object_operator_without_whitespace' => true,
        'single_quote' => true,
        'ternary_operator_spaces' => true,
        'trim_array_spaces' => true,
        'no_spaces_around_offset' => true,
        'whitespace_after_comma_in_array' => true,
        'single_line_comment_style' => [
            'comment_types' => ['hash'],
        ],
        'compact_nullable_type_declaration' => true,
        'no_alternative_syntax' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'phpdoc_order' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'standardize_not_equals' => true,
    ])
    ->setFinder($finder);
