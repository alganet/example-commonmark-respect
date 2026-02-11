<?php

require 'vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use Respect\Validation\Validator;

function get_ast_children($filename): array
{
    $environment = new Environment();
    $environment->addExtension(new CommonMarkCoreExtension());
    $parser = new MarkdownParser($environment);
    $ast = $parser->parse(file_get_contents($filename));
    return iterator_to_array($ast->children());
}

function filter_headings($items): array
{
    return array_values(array_filter($items, static fn ($child) => $child instanceof Heading));
}

function filter_code_blocks($items): array
{
    return array_values(array_filter($items, static fn ($child) => $child instanceof FencedCode));
}

function run_and_capture_output($code): string
{
    ob_start();
    eval($code);
    return ob_get_clean();
}

function make_heading_validator($level, $text): Validator
{
    return v::shortCircuit(
        v::property('level', v::equals($level)),
        v::property('firstChild', v::property('literal', v::equals($text))),
    );
}

$headingsValidator = v::after(
    filter_headings(...),
    v::allOf(
        v::key(0, make_heading_validator(level: 1, text: 'Hello World')),
        v::key(1, make_heading_validator(level: 2, text: 'Description')),
        v::key(2, make_heading_validator(level: 2, text: 'Examples')),
    )
);

$codeBlocksValidator = v::after(
    filter_code_blocks(...),
    v::each(v::allOf(
        v::property('info', v::equals('php')),
        v::property('literal', v::after(
            run_and_capture_output(...),
            v::intVal()
        ))
    ))
);

try {
    $validator = v::after(
        get_ast_children(...),
        v::allOf($headingsValidator, $codeBlocksValidator)
    );
    $validator->assert('example.md');
} catch (Exception $e) {
    print_r($e->getMessages());
    echo $e->getFullMessage() . "\n";
}
