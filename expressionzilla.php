<?php

require 'vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;

try {
    v::after(
        static function ($filename) {
            $environment = new Environment();
            $environment->addExtension(new CommonMarkCoreExtension());
            $parser = new MarkdownParser($environment);
            $ast = $parser->parse(file_get_contents('example.md'));
            return iterator_to_array($ast->children());
        },
        v::named('Markdown structure', v::allOf(
            v::named('Heading structure', v::after(
                static fn ($items) => array_values(array_filter($items, static fn ($child) => $child instanceof Heading)),
                v::allOf(
                    v::key(0, v::factory(static fn ($heading) => v::named(sprintf('Heading at line %s', $heading->getStartLine()), v::shortCircuit(
                        v::property('level', v::equals(1)),
                        v::property('firstChild', v::property('literal', v::equals('Hello World'))),
                    )))),
                    v::key(1, v::factory(static fn ($heading) => v::named(sprintf('Heading at line %s', $heading->getStartLine()), v::shortCircuit(
                        v::property('level', v::equals(2)),
                        v::property('firstChild', v::property('literal', v::equals('Description'))),
                    )))),
                    v::key(2, v::factory(static fn ($heading) => v::named(sprintf('Heading at line %s', $heading->getStartLine()), v::shortCircuit(
                        v::property('level', v::equals(2)),
                        v::property('firstChild', v::property('literal', v::equals('Examples'))),
                    )))),
                )
            )),
            v::after(
                static fn ($items) => array_values(array_filter($items, static fn ($child) => $child instanceof FencedCode)),
                v::templated('Code blocks must pass all the rules', v::each(
                    v::factory(static fn ($code) => v::named(sprintf('Code block at line %s', $code->getStartLine()), v::allOf(
                        v::property('info', v::equals('php')),
                        v::property('literal', v::templated('{{input}} is not a valid code output', v::after(
                            static function ($code) {
                                ob_start();
                                eval($code);
                                return ob_get_clean();
                            },
                            v::intVal()
                        )))
                    )))
                ))
            )
        ))
    )->assert('example.md');
} catch (Exception $e) {
    print_r($e->getMessages());
    echo $e->getFullMessage() . "\n";
}
