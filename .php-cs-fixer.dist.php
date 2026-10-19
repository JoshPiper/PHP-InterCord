<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
	->in([__DIR__ . '/src', __DIR__ . '/tests'])
	->append([__FILE__]);

return (new PhpCsFixer\Config())
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules([
		'@PER-CS3.0' => true,
		'@PER-CS3.0:risky' => true,
		'declare_strict_types' => true,
		'no_unused_imports' => true,
		'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
	])
	->setFinder($finder);
