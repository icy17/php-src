--TEST--
MySQL Prepared Statements and different column counts
--EXTENSIONS--
pdo_mysql
--SKIPIF--
<?php
require_once __DIR__ . '/inc/mysql_pdo_test.inc';
MySQLPDOTest::skip();
?>
--FILE--
<?php
    require_once __DIR__ . '/inc/mysql_pdo_test.inc';
    $db = MySQLPDOTest::factory();

    $procedure = 'pdo_mysql_stmt_variable_columncount_p';

    function check_result($offset, $stmt, $columns) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->nextRowSet();

        if (!isset($row['one']) || ($row['one'] != 1)) {
                printf("[%03d + 1] Expecting array('one' => 1), got %s\n", $offset, var_export($row, true));
                return false;
        }

        if (($columns == 2) &&
            (!isset($row['two']) || ($row['two'] != 2))) {
                printf("[%03d + 2] Expecting array('one' => 1, 'two' => 2), got %s\n", $offset, var_export($row, true));
                return false;
        } else if (($columns == 1) && isset($row['two'])) {
                printf("[%03d + 3] Expecting one array element got two\n", $offset);
                return false;
        }

        return true;
    }

    try {
        // What will happen if a PS returns a different number of result set column upon each execution?
        // Lets try with a SP accepting parameters...
        $db->exec("CREATE PROCEDURE {$procedure}(IN cols INT) BEGIN IF cols < 2 THEN SELECT cols AS 'one'; ELSE SELECT 1 AS 'one', cols AS 'two'; END IF; END;");

        // Emulates PS first
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        $stmt = $db->prepare("CALL {$procedure}(?)");

        $columns = null;
        $stmt->bindParam(1, $columns);
        for ($i = 0; $i < 5; $i++) {
            $columns = ($i % 2) + 1;
            $stmt->execute();
            check_result($i, $stmt, $columns);
        }

        if (MySQLPDOTest::isPDOMySQLnd()) {
            // Native PS
            // Libmysql cannot handle such a stored procedure. You will see leaks with libmysql
            $db = MySQLPDOTest::factory();
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
            $stmt = $db->prepare("CALL {$procedure}(?)");
            $stmt->bindParam(1, $columns);
            for ($i = 5; $i < 10; $i++) {
                $columns = ($i % 2) + 1;
                $stmt->execute();
                check_result($i, $stmt, $columns);
            }
        }

        // And now without parameters... - this gives a different control flow inside PDO
        $db->exec("DROP PROCEDURE IF EXISTS {$procedure}");
        $db->exec("CREATE PROCEDURE {$procedure}() BEGIN DECLARE cols INT; SELECT @numcols INTO cols; IF cols < 2 THEN SET @numcols = 2; SELECT cols AS 'one'; ELSE SET @numcols = 1; SELECT 1 AS 'one', cols AS 'two'; END IF; END;");

                // Emulates PS first
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        $db->exec('SET @numcols = 1');
        $stmt = $db->prepare("CALL {$procedure}()");
        $stmt->execute();
        check_result(11, $stmt, 1);
        $stmt->execute();
        check_result(12, $stmt, 2);
        $db->exec('SET @numcols = 1');
        $stmt->execute();
        check_result(13, $stmt, 1);

        if (MySQLPDOTest::isPDOMySQLnd()) {
            // Native PS
            // Libmysql cannot handle such a stored procedure. You will see leaks with libmysql
            $db = MySQLPDOTest::factory();
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
            $db->exec('SET @numcols = 1');
            $stmt = $db->prepare("CALL {$procedure}()");
            $stmt->execute();
            check_result(14, $stmt, 1);
            $stmt->execute();
            check_result(15, $stmt, 2);
            $db->exec('SET @numcols = 1');
            $stmt->execute();
            check_result(16, $stmt, 1);
        }

    } catch (PDOException $e) {
        printf("[99] %s [%s] %s\n",
            $e->getMessage(), $db->errorCode(), implode(' ', $db->errorInfo()));
    }

    print "done!";
?>
--CLEAN--
<?php
require_once __DIR__ . '/inc/mysql_pdo_test.inc';
$pdo = MySQLPDOTest::factory();
$pdo->query('DROP PROCEDURE IF EXISTS pdo_mysql_stmt_variable_columncount_p');
?>
--EXPECT--
done!
