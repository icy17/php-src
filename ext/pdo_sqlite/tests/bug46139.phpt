--TEST--
Bug #46139 (PDOStatement->setFetchMode() forgets FETCH_PROPS_LATE)
--EXTENSIONS--
pdo_sqlite
--FILE--
<?php

require __DIR__ . '/../../../ext/pdo/tests/pdo_test.inc';
$db = PDOTest::test_factory(__DIR__ . '/common.phpt');

#[AllowDynamicProperties]
class Person {
    public $test_prop = NULL;
    public function __construct() {
        var_dump($this->test_prop);
    }
}

$stmt = $db->query("SELECT 'foo' test_prop, 1");
$stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 'Person');
$r1 = $stmt->fetch();
printf("'%s'\n", $r1->test_prop);

$stmt = $db->query("SELECT 'foo' test_prop, 1");
$stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 'Person');
$r1 = $stmt->fetchAll();
printf("'%s'\n", $r1[0]->test_prop);

$stmt = $db->query("SELECT 'foo' test_prop, 1");
$stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 'Person');
$r1 = $stmt->fetch(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE);
printf("'%s'\n", $r1->test_prop);

?>
--EXPECT--
NULL
'foo'
NULL
'foo'
NULL
'foo'
