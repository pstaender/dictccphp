## About

I'm using a lot `dict.cc` but sometimes I need to query a local instance but missing a client with a useful fuzzy search. Fortunately I found a very helpful [post](http://dannykopping.com/blog/fuzzy-text-search-mysql-jaro-winkler) to implement a fuzzy search in MySQL/MariaDB.

So this is a quick and dirty example for using a language dictionary with fuzzy search (beware that the sarch only helps in english at the moment).

It is **not** ready for public usage, because I didn't put any attention on security issues (of course query values are escaped etc …).

## Requirements

  * PHP 5.4+ (array syntax)
  * newer MySQL or MariaDB (tested against MariaDB 5.5)
  * dictionary data from dict.cc ([download available after signup](http://www1.dict.cc/translation_file_request.php)) (tested with englisch-to-german data)

## Create Database and import function

Assuming that you have root privileges on the mysql-command (`mysql -uroot -pyourpassword`):

```sh
  $ echo "CREATE DATABASE dictcc" | mysql
  $ mysql dictcc < sql/jaro_winkler_similarity.sql
```

## Import dict.cc data to your database

```sh
  $ php tosql.php dict/en_de.txt | mysql dictcc
```

Tables will be named according to the `.txt`-file, in this case `en_de`.

## Querying the dictionary

Edit `config.json` with your credentials and place it in the same dir as `search.php`. Ensure that `search.php` is reachable by a webservice, so that you can make the call:

`http://localhost/dict/search.php?query=good_morning`

With curl

```sh
  $ curl "http://localhost/dict/search.php?query=good_morning"
```

you should get:

```json
[{
  "from": "Good morning!",
  "full": "Good morning!",
  "to": "Guten Tag! [vormittags]",
  "type": "",
  "score": "0.9897435903549194"
}, …]
```

To test the search tolerance for instance, `search.php?query=fuzzyness` should return s.th. like:

```json
[{
  "from": "fuzziness",
  "full": "fuzziness",
  "to": "Unschärfe {f}",
  "type": "noun",
  "score": "0.9555555582046509"
}, {
  "from": "fuzziness",
  "full": "fuzziness",
  "to": "Verschwommenheit {f}",
  "type": "noun",
  "score": "0.9555555582046509"
}, …]
```

## todo's

  * (static) html page for convenient ajax querying
  * query more than one language
  * prepare for "public" usage
  * improve sql statements / perfomance
