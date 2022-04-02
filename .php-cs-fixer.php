<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('tests')
    ->exclude('vendor')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setRules([
        '@Symfony' => true,
        'combine_consecutive_unsets' => true,   //多个unset，合并成一个
        'class_attributes_separation' => true,
        'heredoc_to_nowdoc'                     => true,  //删除配置中多余的空行和/或者空行。
        'no_unreachable_default_argument_value' => false, //在函数参数中，不能有默认值在非缺省值之前的参数。有风险
        'no_useless_else'                       => true,  //删除无用的else
        'no_useless_return'                     => true,  //删除函数末尾无用的return
        'no_empty_phpdoc'                       => true,  // 删除空注释
        'no_empty_statement'                    => true,  //删除多余的分号
        'no_leading_namespace_whitespace'       => true,  //删除namespace声明行包含前导空格
        'no_spaces_inside_parenthesis'          => true,  //删除括号后内两端的空格
        'no_trailing_whitespace'                => true,  //删除非空白行末尾的空白
        'no_unused_imports'                     => true,  //删除未使用的use语句
        'no_whitespace_before_comma_in_array'   => true,  //删除数组声明中，每个逗号前的空格
        'no_whitespace_in_blank_line'           => true,  //删除空白行末尾的空白
        'ordered_class_elements' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'line_ending' => true,
        'single_quote' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);