<?php

/*
 * Script that transforms the dict.cc txt-file(s) to sql format
 * Expects tab-delimited UTF8 (see `http://www1.dict.cc/translation_file_request.php`)
 */

class DictToSQL {

  public $file = '';
  public $content = '';
  public $data = [];
  public $db = "";
  public $table = "";
  public $columns = [
    "from" => "varchar(512) NOT NULL", // first key will be indexed
    "full" => "varchar(512) NOT NULL",
    "to" => "varchar(512) NOT NULL",
    "type" => "varchar(32)"
  ];

  function __construct(array $options = array()) {
    foreach($options as $key => $value)
      $this->{$key} = $value;
  }

  function process() {
    $this->readFile();
    $this->parse();
  }

  function dropTable() {
    echo "DROP TABLE IF EXISTS `{$this->table}`;\n";
  }

  function createTable() {
    $columns = implode(",\n  ", $this->columnsSchema());
    $firstColumn = key($this->columns);
    echo <<<EOT
CREATE TABLE IF NOT EXISTS `{$this->table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  {$columns},
  PRIMARY KEY (`id`),
  KEY `{$firstColumn}` (`{$firstColumn}`)
) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

EOT;
  }

  function createJaronWinklerFunction() {
    echo file_get_contents("./sql/jaro_winkler_similarity.sql");
  }

  function columnsSchema() {
    $a = [];
    foreach ($this->columns as $key => $value) {
      $a[] = "`{$key}` {$value}";
    }
    return $a;
  }

  function columnsAsArray() {
    $a = [];
    foreach ($this->columns as $key => $value) {
      $a[] = $key;
    }
    return $a;
  }

  function columnsLength() {
    $a = [];
    foreach ($this->columns as $key => $value) {
      $a[] = (int) preg_replace("/^.*\((\d+)\).*$/","\\1", $value);
    }
    return $a;
  }

  function readFile() {
    if ($this->file)
      $this->content = file_get_contents($this->file);
  }

  function parse() {
    if ($this->content) {
      $columnsLength = $this->columnsLength();
      $columns = $this->columnsAsArray();
      $table = mysql_real_escape_string($this->table);
      foreach (explode("\n", $this->content) as $line) {
        // skip comment line(s)
        if (preg_match("/^\s*#/", $line))
          continue;
        $parts = explode("\t", $line);
        // skip if we have columns count mismatch
        if (count($parts) != 3)
          continue;
        // strip additional informations like `(to be) … [Amer.]`
        $from = $parts[0];
        $from = preg_replace("/^\s*\(.+\)\s*/", "", $from);
        $from = preg_replace("/\s*(\[.+\]|\<.+\>)\s*$/", "", $from);
        array_unshift($parts, $from);
        // escape values for db
        // and check that values aren't larger than columns in db
        $i = 0;
        foreach ($parts as &$part) {
          $part = mysql_real_escape_string($part);
          if (!(strlen($part) <= $columnsLength[$i]))
            user_error("Value `".$part."` is larger than allowed (".$columnsLength[$i]."), you need to adjust varchar length for `{$columns[$i]}`");
          $i++;
        }
        echo "\nINSERT INTO `{$table}` ( `id`, `".implode('`, `', $columns)."` ) VALUES ( NULL, '".implode("', '", $parts)."' );";
      }
    }
  }
}

if (php_sapi_name() != 'cli' )
  exit("Script needs to be executed from shell");

if ((!isset($argv[1])) || (!is_readable($argv[1])))
  exit("Usage: php ".$_SERVER['SCRIPT_NAME']." \$pathToCSVFile\nPlease ensure that the file is readable!");

$import = new DictToSQL([
  "file" => $argv[1],
  "table" => pathinfo($argv[1])['filename'],
]);

$import->dropTable();
$import->createTable();
$import->process();
