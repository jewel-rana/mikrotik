--TEST--
Test for PEAR2\Console\CommandLine::parse() method (invalid subcommand detection).
--SKIPIF--
<?php if (php_sapi_name()!='cli') {
    echo 'skip';
} ?>
--ARGS--
foo
--FILE--
<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tests.inc.php';

$parser = buildParser4();
try {
    $result = $parser->parse();
} catch (Exception $exc) {
    echo $exc->getMessage();
}

?>
--EXPECT--
Command "foo" is not valid.
