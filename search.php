<?php

// read config json
$config = json_decode(file_get_contents("config.json"));

header('Content-type: application/json');

// allowed parameters: `?query=…` or `?q=…`
$queryString = (isset($_GET['query'])) ? $_GET['query'] : $_GET['q'];

if (isset($queryString)) {
  $mysql = mysql_connect($config->db->host, $config->db->username, $config->db->password);
  $query = trim(mysql_real_escape_string($queryString));
  // strip `%`
  $query = preg_replace("/[\%]+/", "", $query);
  // replace `+_` with whitespace(s)
  $query = preg_replace("/[_+]+/", " ", $query);

  // check query string
  if (strlen($query) < $config->minimumStringLength)
    exit(json_encode([]));

  mysql_select_db($config->db->database, $mysql);
  $queryStrings = [
    "SELECT *, jaro_winkler_similarity(`from`, '{$query}') AS score FROM en_de
     WHERE `from` LIKE '{$query}'
     LIMIT {$config->limit}",
    "
    SELECT * FROM (
      SELECT DISTINCT *, jaro_winkler_similarity(`from`, '{$query}') AS score
      FROM (SELECT * FROM en_de WHERE `from` LIKE '%{$query}%') AS likeMatches
      ORDER BY score DESC
      LIMIT {$config->limit}
    ) AS similarWord WHERE similarWord.score >= {$config->minimumScore}",
    "
    SELECT * FROM (
      SELECT DISTINCT *, jaro_winkler_similarity(`from`, '{$query}') AS score
      FROM (SELECT * FROM en_de WHERE `from` LIKE '".$query[0]."%".$query[strlen($query)-1]."') AS likeMatches
      ORDER BY score DESC
      LIMIT {$config->limit}
    ) AS similarWord WHERE similarWord.score >= {$config->minimumScore}",
  ];
  // print_r($queryStrings);
  foreach($queryStrings as $queryString) {
    $result = mysql_query($queryString);
    if ((!$result) || (mysql_num_rows($result) == 0)) {
      // try more fuzzy
      continue;
    }
    if ($result) {
      $rows = [];
      $publicColumns = explode(",", $config->publicColumns);
      while ($row = mysql_fetch_assoc($result)) {
        $data = [];
        // display only allowed columns
        foreach($publicColumns as $column)
          if (isset($row[$column]))
            $data[$column] = $row[$column];
        $rows[] = $data;
      }
      exit(json_encode($rows));
    } else {
      $err = [
        "number" => mysql_errno($mysql),
        "message" => mysql_error($mysql),
      ];
      if ($err['message']) {
        exit(json_encode([ "error" => $err ]));
      }
    }
  }
  exit(json_encode([]));
}
