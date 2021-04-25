<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004-2018 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script configures PmWiki to use utf-8 in page content and
    pagenames.  There are some unfortunate side effects about PHP's
    utf-8 implementation, however.  First, since PHP doesn't have a
    way to do pattern matching on upper/lowercase UTF-8 characters,
    WikiWords are limited to the ASCII-7 set, and all links to page
    names with UTF-8 characters have to be in double brackets.
    Second, we have to assume that all non-ASCII characters are valid
    in pagenames, since there's no way to determine which UTF-8
    characters are "letters" and which are punctuation.
    
    Script maintained by Petko YOTOV www.pmwiki.org/petko
*/

global $HTTPHeaders, $KeepToken, $pagename,
  $GroupPattern, $NamePattern, $WikiWordPattern, $SuffixPattern,
  $PageNameChars, $MakePageNamePatterns, $CaseConversions, $StringFolding,
  $Charset, $HTMLHeaderFmt, $StrFoldFunction, $AsSpacedFunction;

$Charset = 'UTF-8';
$HTTPHeaders['utf-8'] = 'Content-type: text/html; charset=UTF-8';
$HTMLHeaderFmt['utf-8'] = 
  "<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />";
SDVA($HTMLStylesFmt, array('rtl-ltr' => "
  .rtl, .rtl * {direction:rtl; unicode-bidi:bidi-override;}
  .ltr, .ltr * {direction:ltr; unicode-bidi:bidi-override;}
  .rtl .indent, .rtl.indent, .rtl .outdent, .rtl.outdent {
    margin-left:0; margin-right: 40px;
  }
  "));
$pagename = @$_REQUEST['n'];
if (!$pagename) $pagename = @$_REQUEST['pagename'];
if (!$pagename &&
      preg_match('!^'.preg_quote($_SERVER['SCRIPT_NAME'],'!').'/?([^?]*)!',
          $_SERVER['REQUEST_URI'],$match))
    $pagename = urldecode($match[1]);
$pagename_unfiltered = $pagename;
$pagename = preg_replace('![${}\'"\\\\]+!', '', $pagename);
$FmtPV['$RequestedPage'] = 'PHSC($GLOBALS["pagename_unfiltered"], ENT_QUOTES)';

$GroupPattern = '[\\w\\x80-\\xfe]+(?:-[\\w\\x80-\\xfe]+)*';
$NamePattern = '[\\w\\x80-\\xfe]+(?:-[\\w\\x80-\\xfe]+)*';
$WikiWordPattern = 
  '[A-Z][A-Za-z0-9]*(?:[A-Z][a-z0-9]|[a-z0-9][A-Z])[A-Za-z0-9]*';
$SuffixPattern = '(?:-?[A-Za-z0-9\\x80-\\xd6]+)*';

SDV($PageNameChars, '-[:alnum:]\\x80-\\xfe');
SDV($MakePageNamePatterns, array(
    '/[?#].*$/' => '',                     # strip everything after ? or #
    "/'/" => '',                           # strip single-quotes
    "/[^$PageNameChars]+/" => ' ',         # convert everything else to space
    '/(?<=^| )([a-z])/' => 'cb_toupper',
    '/(?<=^| )([\\xc0-\\xdf].)/' => 'utf8toupper',
    '/ /' => ''));
SDV($StrFoldFunction, 'utf8fold');

$AsSpacedFunction = 'AsSpacedUTF8';

function utf8toupper($x) {
  if(is_array($x)) $x = $x[1];
  global $CaseConversions;
  if (strlen($x) <= 2 && @$CaseConversions[$x])
    return $CaseConversions[$x];
  static $lower, $upper;
  if (!@$lower) { 
    $lower = array_keys($CaseConversions); 
    $upper = array_values($CaseConversions);
  }
  return str_replace($lower, $upper, $x);
}


function utf8fold($x) {
  global $StringFolding;
  static $source, $target;
  if (!@$source) {
    $source = array_keys($StringFolding);
    $target = array_values($StringFolding);
  }
  return str_replace($source, $target, $x);
}


function AsSpacedUTF8($text) {
  global $CaseConversions;
  static $lower, $upper;
  if (!@$CaseConversions) return AsSpaced($text);
  if (!@$lower) {
    $lower = implode('|', array_keys($CaseConversions));
    $upper = implode('|', array_values($CaseConversions));
  }
  $text = preg_replace("/($lower|\\d)($upper)/", '$1 $2', $text);
  $text = preg_replace('/([^-\\d])(\\d[-\\d]*( |$))/', '$1 $2', $text);
  return preg_replace("/($upper)(($upper)($lower|\\d))/", '$1 $2', $text);
}


SDVA($MarkupExpr, array(
  'substr'  => 'call_user_func_array("utf8string", $args)',
  'strlen'  => 'utf8string($args[0], "strlen")',
  'ucfirst' => 'utf8string($args[0], "ucfirst")',
  'ucwords' => 'utf8string($args[0], "ucwords")',
  'tolower' => 'utf8string($args[0], "tolower")',
  'toupper' => 'utf8string($args[0], "toupper")',
));

function utf8string($str, $start=false, $len=false) { # strlen+substr++ combo for UTF-8
  global $CaseConversions;
  static $lower;
  if (!@$lower) $lower = implode('|', array_keys($CaseConversions));
  $ascii = preg_match('/[\\x80-\\xFF]/', $str)? 0:1;
  switch ((string)$start) {
    case 'ucfirst': return $ascii ? ucfirst($str) :
      preg_replace_callback("/(^)($lower)/", 'cb_uc_first_words', $str);
    case 'ucwords': return $ascii ? ucwords($str) :
      preg_replace_callback("/(^|\\s+)($lower)/", 'cb_uc_first_words', $str);
    case 'tolower': return $ascii ? strtolower($str) : utf8fold($str);
    case 'toupper': return $ascii ? strtoupper($str) : utf8toupper($str);
  }
  if ($ascii) {
    if ($start==='strlen') return strlen($str);
    if ($len===false) return substr($str, intval($start));
    return substr($str, intval($start), intval($len));
  }
  $letters = preg_split("/([\\x00-\\x7f]|[\\xc2-\\xdf].|[\\xe0-\\xef]..|[\\xf0-\\xf4]...)/", 
              $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
  if ($start==='strlen') return count($letters);
  if ($len===false) return implode('', array_slice($letters, $start));
  return implode('', array_slice($letters, $start, $len));
}
function cb_uc_first_words($m){
  return $m[1].$GLOBALS["CaseConversions"][$m[2]];
}
##   Conversion tables.  
##   $CaseConversion maps lowercase utf8 sequences to 
##   their uppercase equivalents.  The table was derived from [1].
##   $StringFolding normalizes strings so that "equivalent"
##   forms will match using a binary comparison (derived from [2]).
##     [1] http://unicode.org/Public/UNIDATA/UnicodeData.txt
##     [2] http://unicode.org/Public/UNIDATA/CaseFolding.txt

SDV($CaseConversions, array(
    ##   U+0060
    "a" => "A", "b" => "B", "c" => "C", "d" => "D", "e" => "E", "f" => "F",
    "g" => "G", "h" => "H", "i" => "I", "j" => "J", "k" => "K", "l" => "L",
    "m" => "M", "n" => "N", "o" => "O", "p" => "P", "q" => "Q", "r" => "R",
    "s" => "S", "t" => "T", "u" => "U", "v" => "V", "w" => "W", "x" => "X",
    "y" => "Y", "z" => "Z",
    ##   U+00b5
    "\xc2\xb5" => "\xce\x9c",
    ##   U+00E0 to U+00FF
    "\xc3\xa0" => "\xc3\x80",  "\xc3\xa1" => "\xc3\x81",
    "\xc3\xa2" => "\xc3\x82",  "\xc3\xa3" => "\xc3\x83",
    "\xc3\xa4" => "\xc3\x84",  "\xc3\xa5" => "\xc3\x85",
    "\xc3\xa6" => "\xc3\x86",  "\xc3\xa7" => "\xc3\x87",
    "\xc3\xa8" => "\xc3\x88",  "\xc3\xa9" => "\xc3\x89",
    "\xc3\xaa" => "\xc3\x8a",  "\xc3\xab" => "\xc3\x8b",
    "\xc3\xac" => "\xc3\x8c",  "\xc3\xad" => "\xc3\x8d",
    "\xc3\xae" => "\xc3\x8e",  "\xc3\xaf" => "\xc3\x8f",
    "\xc3\xb0" => "\xc3\x90",  "\xc3\xb1" => "\xc3\x91",
    "\xc3\xb2" => "\xc3\x92",  "\xc3\xb3" => "\xc3\x93",
    "\xc3\xb4" => "\xc3\x94",  "\xc3\xb5" => "\xc3\x95",
    "\xc3\xb6" => "\xc3\x96",  "\xc3\xb8" => "\xc3\x98",
    "\xc3\xb9" => "\xc3\x99",  "\xc3\xba" => "\xc3\x9a",
    "\xc3\xbb" => "\xc3\x9b",  "\xc3\xbc" => "\xc3\x9c",
    "\xc3\xbd" => "\xc3\x9d",  "\xc3\xbe" => "\xc3\x9e",
    "\xc3\xbf" => "\xc5\xb8",  
    ##   U+0100
    "\xc4\x81" => "\xc4\x80",  "\xc4\x83" => "\xc4\x82",
    "\xc4\x85" => "\xc4\x84",  "\xc4\x87" => "\xc4\x86",
    "\xc4\x89" => "\xc4\x88",  "\xc4\x8b" => "\xc4\x8a",
    "\xc4\x8d" => "\xc4\x8c",  "\xc4\x8f" => "\xc4\x8e",
    "\xc4\x91" => "\xc4\x90",  "\xc4\x93" => "\xc4\x92",
    "\xc4\x95" => "\xc4\x94",  "\xc4\x97" => "\xc4\x96",
    "\xc4\x99" => "\xc4\x98",  "\xc4\x9b" => "\xc4\x9a",
    "\xc4\x9d" => "\xc4\x9c",  "\xc4\x9f" => "\xc4\x9e",
    "\xc4\xa1" => "\xc4\xa0",  "\xc4\xa3" => "\xc4\xa2",
    "\xc4\xa5" => "\xc4\xa4",  "\xc4\xa7" => "\xc4\xa6",
    "\xc4\xa9" => "\xc4\xa8",  "\xc4\xab" => "\xc4\xaa",
    "\xc4\xad" => "\xc4\xac",  "\xc4\xaf" => "\xc4\xae",
    "\xc4\xb1" => "I",         "\xc4\xb3" => "\xc4\xb2",
    "\xc4\xb5" => "\xc4\xb4",  "\xc4\xb7" => "\xc4\xb6",
    "\xc4\xba" => "\xc4\xb9",  "\xc4\xbc" => "\xc4\xbb",
    "\xc4\xbe" => "\xc4\xbd",  
    ##   U+0140
    "\xc5\x80" => "\xc4\xbf",  "\xc5\x82" => "\xc5\x81", 
    "\xc5\x84" => "\xc5\x83",  "\xc5\x86" => "\xc5\x85", 
    "\xc5\x88" => "\xc5\x87",  "\xc5\x8b" => "\xc5\x8a",
    "\xc5\x8d" => "\xc5\x8c",  "\xc5\x8f" => "\xc5\x8e",
    "\xc5\x91" => "\xc5\x90",  "\xc5\x93" => "\xc5\x92",
    "\xc5\x95" => "\xc5\x94",  "\xc5\x97" => "\xc5\x96",
    "\xc5\x99" => "\xc5\x98",  "\xc5\x9b" => "\xc5\x9a",
    "\xc5\x9d" => "\xc5\x9c",  "\xc5\x9f" => "\xc5\x9e",
    "\xc5\xa1" => "\xc5\xa0",  "\xc5\xa3" => "\xc5\xa2",
    "\xc5\xa5" => "\xc5\xa4",  "\xc5\xa7" => "\xc5\xa6",
    "\xc5\xa9" => "\xc5\xa8",  "\xc5\xab" => "\xc5\xaa",
    "\xc5\xad" => "\xc5\xac",  "\xc5\xaf" => "\xc5\xae",
    "\xc5\xb1" => "\xc5\xb0",  "\xc5\xb3" => "\xc5\xb2",
    "\xc5\xb5" => "\xc5\xb4",  "\xc5\xb7" => "\xc5\xb6",
    "\xc5\xba" => "\xc5\xb9",  "\xc5\xbc" => "\xc5\xbb",
    "\xc5\xbe" => "\xc5\xbd",  "\xc5\xbf" => "S",
    ##   U+0180
    "\xc6\x80" => "\xc9\x83",  "\xc6\x83" => "\xc6\x82",
    "\xc6\x85" => "\xc6\x84",  "\xc6\x88" => "\xc6\x87",
    "\xc6\x8c" => "\xc6\x8b",  "\xc6\x92" => "\xc6\x91",
    "\xc6\x95" => "\xc7\xb6",  "\xc6\x99" => "\xc6\x98",
    "\xc6\x9a" => "\xc8\xbd",  "\xc6\x9e" => "\xc8\xa0",
    "\xc6\xa1" => "\xc6\xa0",  "\xc6\xa3" => "\xc6\xa2",
    "\xc6\xa5" => "\xc6\xa4",  "\xc6\xa8" => "\xc6\xa7",
    "\xc6\xad" => "\xc6\xac",  "\xc6\xb0" => "\xc6\xaf",
    "\xc6\xb4" => "\xc6\xb3",  "\xc6\xb6" => "\xc6\xb5",
    "\xc6\xb9" => "\xc6\xb8",  "\xc6\xbd" => "\xc6\xbc",
    "\xc6\xbf" => "\xc7\xb7",  
    ##   U+01c0
    "\xc7\x85" => "\xc7\x84",  "\xc7\x86" => "\xc7\x84", 
    "\xc7\x88" => "\xc7\x87",  "\xc7\x89" => "\xc7\x87",
    "\xc7\x8b" => "\xc7\x8a",  "\xc7\x8c" => "\xc7\x8a",
    "\xc7\x8e" => "\xc7\x8d",  "\xc7\x90" => "\xc7\x8f",
    "\xc7\x92" => "\xc7\x91",  "\xc7\x94" => "\xc7\x93",
    "\xc7\x96" => "\xc7\x95",  "\xc7\x98" => "\xc7\x97",
    "\xc7\x9a" => "\xc7\x99",  "\xc7\x9c" => "\xc7\x9b",
    "\xc7\x9d" => "\xc6\x8e",  "\xc7\x9f" => "\xc7\x9e",
    "\xc7\xa1" => "\xc7\xa0",  "\xc7\xa3" => "\xc7\xa2",
    "\xc7\xa5" => "\xc7\xa4",  "\xc7\xa7" => "\xc7\xa6",
    "\xc7\xa9" => "\xc7\xa8",  "\xc7\xab" => "\xc7\xaa",
    "\xc7\xad" => "\xc7\xac",  "\xc7\xaf" => "\xc7\xae",
    "\xc7\xb2" => "\xc7\xb1",  "\xc7\xb3" => "\xc7\xb1",
    "\xc7\xb5" => "\xc7\xb4",  "\xc7\xb9" => "\xc7\xb8",
    "\xc7\xbb" => "\xc7\xba",  "\xc7\xbd" => "\xc7\xbc",
    "\xc7\xbf" => "\xc7\xbe",
    ##   U+0200
    "\xc8\x81" => "\xc8\x80",  "\xc8\x83" => "\xc8\x82",
    "\xc8\x85" => "\xc8\x84",  "\xc8\x87" => "\xc8\x86",
    "\xc8\x89" => "\xc8\x88",  "\xc8\x8b" => "\xc8\x8a",
    "\xc8\x8d" => "\xc8\x8c",  "\xc8\x8f" => "\xc8\x8e",
    "\xc8\x91" => "\xc8\x90",  "\xc8\x93" => "\xc8\x92",
    "\xc8\x95" => "\xc8\x94",  "\xc8\x97" => "\xc8\x96",
    "\xc8\x99" => "\xc8\x98",  "\xc8\x9b" => "\xc8\x9a",
    "\xc8\x9d" => "\xc8\x9c",  "\xc8\x9f" => "\xc8\x9e",
    "\xc8\xa3" => "\xc8\xa2",  "\xc8\xa5" => "\xc8\xa4",
    "\xc8\xa7" => "\xc8\xa6",  "\xc8\xa9" => "\xc8\xa8",
    "\xc8\xab" => "\xc8\xaa",  "\xc8\xad" => "\xc8\xac",
    "\xc8\xaf" => "\xc8\xae",  "\xc8\xb1" => "\xc8\xb0",
    "\xc8\xb3" => "\xc8\xb2",  "\xc8\xbc" => "\xc8\xbb",
    ##   U+0240
    "\xc9\x82" => "\xc9\x81",  "\xc9\x87" => "\xc9\x86",
    "\xc9\x89" => "\xc9\x88",  "\xc9\x8b" => "\xc9\x8a",
    "\xc9\x8d" => "\xc9\x8c",  "\xc9\x8f" => "\xc9\x8e",
    "\xc9\x93" => "\xc6\x81",  "\xc9\x94" => "\xc6\x86",
    "\xc9\x96" => "\xc6\x89",  "\xc9\x97" => "\xc6\x8a",
    "\xc9\x99" => "\xc6\x8f",  "\xc9\x9b" => "\xc6\x90",
    "\xc9\xa0" => "\xc6\x93",  "\xc9\xa3" => "\xc6\x94",
    "\xc9\xa8" => "\xc6\x97",  "\xc9\xa9" => "\xc6\x96",
    "\xc9\xab" => "\xe2\xb1\xa2",  "\xc9\xaf" => "\xc6\x9c",  
    "\xc9\xb2" => "\xc6\x9d",  "\xc9\xb5" => "\xc6\x9f",  
    "\xc9\xbd" => "\xe2\xb1\xa4",
    ##   U+0280
    "\xca\x80" => "\xc6\xa6",  "\xca\x83" => "\xc6\xa9",
    "\xca\x88" => "\xc6\xae",  "\xca\x89" => "\xc9\x84",
    "\xca\x8a" => "\xc6\xb1",  "\xca\x8b" => "\xc6\xb2",
    "\xca\x8c" => "\xc9\x85",  "\xca\x92" => "\xc6\xb7",
    ##   U+0340
    "\xcd\x85" => "\xce\x99",  "\xcd\xbb" => "\xcf\xbd",
    "\xcd\xbc" => "\xcf\xbe",  "\xcd\xbd" => "\xcf\xbf",
    ##   U+0380
    "\xce\xac" => "\xce\x86",  "\xce\xad" => "\xce\x88",
    "\xce\xae" => "\xce\x89",  "\xce\xaf" => "\xce\x8a",
    "\xce\xb1" => "\xce\x91",  "\xce\xb2" => "\xce\x92",
    "\xce\xb3" => "\xce\x93",  "\xce\xb4" => "\xce\x94",
    "\xce\xb5" => "\xce\x95",  "\xce\xb6" => "\xce\x96",
    "\xce\xb7" => "\xce\x97",  "\xce\xb8" => "\xce\x98",
    "\xce\xb9" => "\xce\x99",  "\xce\xba" => "\xce\x9a",
    "\xce\xbb" => "\xce\x9b",  "\xce\xbc" => "\xce\x9c",
    "\xce\xbd" => "\xce\x9d",  "\xce\xbe" => "\xce\x9e",
    "\xce\xbf" => "\xce\x9f",  
    ##   U+03c0
    "\xcf\x80" => "\xce\xa0",  "\xcf\x81" => "\xce\xa1",
    "\xcf\x82" => "\xce\xa3",  "\xcf\x83" => "\xce\xa3",
    "\xcf\x84" => "\xce\xa4",  "\xcf\x85" => "\xce\xa5",
    "\xcf\x86" => "\xce\xa6",  "\xcf\x87" => "\xce\xa7",
    "\xcf\x88" => "\xce\xa8",  "\xcf\x89" => "\xce\xa9",
    "\xcf\x8a" => "\xce\xaa",  "\xcf\x8b" => "\xce\xab",
    "\xcf\x8c" => "\xce\x8c",  "\xcf\x8d" => "\xce\x8e",
    "\xcf\x8e" => "\xce\x8f",  "\xcf\x90" => "\xce\x92",
    "\xcf\x91" => "\xce\x98",  "\xcf\x95" => "\xce\xa6",
    "\xcf\x96" => "\xce\xa0",  "\xcf\x99" => "\xcf\x98",
    "\xcf\x9b" => "\xcf\x9a",  "\xcf\x9d" => "\xcf\x9c",
    "\xcf\x9f" => "\xcf\x9e",  "\xcf\xa1" => "\xcf\xa0",
    "\xcf\xa3" => "\xcf\xa2",  "\xcf\xa5" => "\xcf\xa4",
    "\xcf\xa7" => "\xcf\xa6",  "\xcf\xa9" => "\xcf\xa8",
    "\xcf\xab" => "\xcf\xaa",  "\xcf\xad" => "\xcf\xac",
    "\xcf\xaf" => "\xcf\xae",  "\xcf\xb0" => "\xce\x9a",
    "\xcf\xb1" => "\xce\xa1",  "\xcf\xb2" => "\xcf\xb9",
    "\xcf\xb5" => "\xce\x95",  "\xcf\xb8" => "\xcf\xb7",
    "\xcf\xbb" => "\xcf\xba",
    ##   U+0400
    "\xd0\xb0" => "\xd0\x90",  "\xd0\xb1" => "\xd0\x91",
    "\xd0\xb2" => "\xd0\x92",  "\xd0\xb3" => "\xd0\x93",
    "\xd0\xb4" => "\xd0\x94",  "\xd0\xb5" => "\xd0\x95",
    "\xd0\xb6" => "\xd0\x96",  "\xd0\xb7" => "\xd0\x97",
    "\xd0\xb8" => "\xd0\x98",  "\xd0\xb9" => "\xd0\x99",
    "\xd0\xba" => "\xd0\x9a",  "\xd0\xbb" => "\xd0\x9b",
    "\xd0\xbc" => "\xd0\x9c",  "\xd0\xbd" => "\xd0\x9d",
    "\xd0\xbe" => "\xd0\x9e",  "\xd0\xbf" => "\xd0\x9f",
    ##   U+0440
    "\xd1\x80" => "\xd0\xa0",  "\xd1\x81" => "\xd0\xa1",
    "\xd1\x82" => "\xd0\xa2",  "\xd1\x83" => "\xd0\xa3",
    "\xd1\x84" => "\xd0\xa4",  "\xd1\x85" => "\xd0\xa5",
    "\xd1\x86" => "\xd0\xa6",  "\xd1\x87" => "\xd0\xa7",
    "\xd1\x88" => "\xd0\xa8",  "\xd1\x89" => "\xd0\xa9",
    "\xd1\x8a" => "\xd0\xaa",  "\xd1\x8b" => "\xd0\xab",
    "\xd1\x8c" => "\xd0\xac",  "\xd1\x8d" => "\xd0\xad",
    "\xd1\x8e" => "\xd0\xae",  "\xd1\x8f" => "\xd0\xaf",
    "\xd1\x90" => "\xd0\x80",  "\xd1\x91" => "\xd0\x81",
    "\xd1\x92" => "\xd0\x82",  "\xd1\x93" => "\xd0\x83",
    "\xd1\x94" => "\xd0\x84",  "\xd1\x95" => "\xd0\x85",
    "\xd1\x96" => "\xd0\x86",  "\xd1\x97" => "\xd0\x87",
    "\xd1\x98" => "\xd0\x88",  "\xd1\x99" => "\xd0\x89",
    "\xd1\x9a" => "\xd0\x8a",  "\xd1\x9b" => "\xd0\x8b",
    "\xd1\x9c" => "\xd0\x8c",  "\xd1\x9d" => "\xd0\x8d",
    "\xd1\x9e" => "\xd0\x8e",  "\xd1\x9f" => "\xd0\x8f",
    "\xd1\xa1" => "\xd1\xa0",  "\xd1\xa3" => "\xd1\xa2",
    "\xd1\xa5" => "\xd1\xa4",  "\xd1\xa7" => "\xd1\xa6",
    "\xd1\xa9" => "\xd1\xa8",  "\xd1\xab" => "\xd1\xaa",
    "\xd1\xad" => "\xd1\xac",  "\xd1\xaf" => "\xd1\xae",
    "\xd1\xb1" => "\xd1\xb0",  "\xd1\xb3" => "\xd1\xb2",
    "\xd1\xb5" => "\xd1\xb4",  "\xd1\xb7" => "\xd1\xb6",
    "\xd1\xb9" => "\xd1\xb8",  "\xd1\xbb" => "\xd1\xba",
    "\xd1\xbd" => "\xd1\xbc",  "\xd1\xbf" => "\xd1\xbe",
    ##   U+0480
    "\xd2\x81" => "\xd2\x80",  "\xd2\x8b" => "\xd2\x8a",
    "\xd2\x8d" => "\xd2\x8c",  "\xd2\x8f" => "\xd2\x8e",
    "\xd2\x91" => "\xd2\x90",  "\xd2\x93" => "\xd2\x92",
    "\xd2\x95" => "\xd2\x94",  "\xd2\x97" => "\xd2\x96",
    "\xd2\x99" => "\xd2\x98",  "\xd2\x9b" => "\xd2\x9a",
    "\xd2\x9d" => "\xd2\x9c",  "\xd2\x9f" => "\xd2\x9e",
    "\xd2\xa1" => "\xd2\xa0",  "\xd2\xa3" => "\xd2\xa2",
    "\xd2\xa5" => "\xd2\xa4",  "\xd2\xa7" => "\xd2\xa6",
    "\xd2\xa9" => "\xd2\xa8",  "\xd2\xab" => "\xd2\xaa",
    "\xd2\xad" => "\xd2\xac",  "\xd2\xaf" => "\xd2\xae",
    "\xd2\xb1" => "\xd2\xb0",  "\xd2\xb3" => "\xd2\xb2",
    "\xd2\xb5" => "\xd2\xb4",  "\xd2\xb7" => "\xd2\xb6",
    "\xd2\xb9" => "\xd2\xb8",  "\xd2\xbb" => "\xd2\xba",
    "\xd2\xbd" => "\xd2\xbc",  "\xd2\xbf" => "\xd2\xbe",
    ##   U+04c0
    "\xd3\x82" => "\xd3\x81",  "\xd3\x84" => "\xd3\x83",
    "\xd3\x86" => "\xd3\x85",  "\xd3\x88" => "\xd3\x87",
    "\xd3\x8a" => "\xd3\x89",  "\xd3\x8c" => "\xd3\x8b",
    "\xd3\x8e" => "\xd3\x8d",  "\xd3\x8f" => "\xd3\x80",
    "\xd3\x91" => "\xd3\x90",  "\xd3\x93" => "\xd3\x92",
    "\xd3\x95" => "\xd3\x94",  "\xd3\x97" => "\xd3\x96",
    "\xd3\x99" => "\xd3\x98",  "\xd3\x9b" => "\xd3\x9a",
    "\xd3\x9d" => "\xd3\x9c",  "\xd3\x9f" => "\xd3\x9e",
    "\xd3\xa1" => "\xd3\xa0",  "\xd3\xa3" => "\xd3\xa2",
    "\xd3\xa5" => "\xd3\xa4",  "\xd3\xa7" => "\xd3\xa6",
    "\xd3\xa9" => "\xd3\xa8",  "\xd3\xab" => "\xd3\xaa",
    "\xd3\xad" => "\xd3\xac",  "\xd3\xaf" => "\xd3\xae",
    "\xd3\xb1" => "\xd3\xb0",  "\xd3\xb3" => "\xd3\xb2",
    "\xd3\xb5" => "\xd3\xb4",  "\xd3\xb7" => "\xd3\xb6",
    "\xd3\xb9" => "\xd3\xb8",  "\xd3\xbb" => "\xd3\xba",
    "\xd3\xbd" => "\xd3\xbc",  "\xd3\xbf" => "\xd3\xbe",
    ##   U+0500
    "\xd4\x81" => "\xd4\x80",  "\xd4\x83" => "\xd4\x82",
    "\xd4\x85" => "\xd4\x84",  "\xd4\x87" => "\xd4\x86",
    "\xd4\x89" => "\xd4\x88",  "\xd4\x8b" => "\xd4\x8a",
    "\xd4\x8d" => "\xd4\x8c",  "\xd4\x8f" => "\xd4\x8e",
    "\xd4\x91" => "\xd4\x90",  "\xd4\x93" => "\xd4\x92",
    ##   U+0560
    "\xd5\xa1" => "\xd4\xb1",  "\xd5\xa2" => "\xd4\xb2",
    "\xd5\xa3" => "\xd4\xb3",  "\xd5\xa4" => "\xd4\xb4",
    "\xd5\xa5" => "\xd4\xb5",  "\xd5\xa6" => "\xd4\xb6",
    "\xd5\xa7" => "\xd4\xb7",  "\xd5\xa8" => "\xd4\xb8",
    "\xd5\xa9" => "\xd4\xb9",  "\xd5\xaa" => "\xd4\xba",
    "\xd5\xab" => "\xd4\xbb",  "\xd5\xac" => "\xd4\xbc",
    "\xd5\xad" => "\xd4\xbd",  "\xd5\xae" => "\xd4\xbe",
    "\xd5\xaf" => "\xd4\xbf",  "\xd5\xb0" => "\xd5\x80",
    "\xd5\xb1" => "\xd5\x81",  "\xd5\xb2" => "\xd5\x82",
    "\xd5\xb3" => "\xd5\x83",  "\xd5\xb4" => "\xd5\x84",
    "\xd5\xb5" => "\xd5\x85",  "\xd5\xb6" => "\xd5\x86",
    "\xd5\xb7" => "\xd5\x87",  "\xd5\xb8" => "\xd5\x88",
    "\xd5\xb9" => "\xd5\x89",  "\xd5\xba" => "\xd5\x8a",
    "\xd5\xbb" => "\xd5\x8b",  "\xd5\xbc" => "\xd5\x8c",
    "\xd5\xbd" => "\xd5\x8d",  "\xd5\xbe" => "\xd5\x8e",
    "\xd5\xbf" => "\xd5\x8f",
    ##   U+0580
    "\xd6\x80" => "\xd5\x90",  "\xd6\x81" => "\xd5\x91",
    "\xd6\x82" => "\xd5\x92",  "\xd6\x83" => "\xd5\x93",
    "\xd6\x84" => "\xd5\x94",  "\xd6\x85" => "\xd5\x95",
    "\xd6\x86" => "\xd5\x96"
  ));

SDV($StringFolding, array(
    ##   U+0040
    "A" => "a", "B" => "b", "C" => "c", "D" => "d", "E" => "e", "F" => "f",
    "G" => "g", "H" => "h", "I" => "i", "J" => "j", "K" => "k", "L" => "l",
    "M" => "m", "N" => "n", "O" => "o", "P" => "p", "Q" => "q", "R" => "r",
    "S" => "s", "T" => "t", "U" => "u", "V" => "v", "W" => "w", "X" => "x",
    "Y" => "y", "Z" => "z",
    ##   U+00B5
    "\xc2\xb5" => "\xce\xbc",
    ##   U+00C0
    "\xc3\x80" => "\xc3\xa0",  "\xc3\x81" => "\xc3\xa1",
    "\xc3\x82" => "\xc3\xa2",  "\xc3\x83" => "\xc3\xa3",
    "\xc3\x84" => "\xc3\xa4",  "\xc3\x85" => "\xc3\xa5",
    "\xc3\x86" => "\xc3\xa6",  "\xc3\x87" => "\xc3\xa7",
    "\xc3\x88" => "\xc3\xa8",  "\xc3\x89" => "\xc3\xa9",
    "\xc3\x8a" => "\xc3\xaa",  "\xc3\x8b" => "\xc3\xab",
    "\xc3\x8c" => "\xc3\xac",  "\xc3\x8d" => "\xc3\xad",
    "\xc3\x8e" => "\xc3\xae",  "\xc3\x8f" => "\xc3\xaf",
    "\xc3\x90" => "\xc3\xb0",  "\xc3\x91" => "\xc3\xb1",
    "\xc3\x92" => "\xc3\xb2",  "\xc3\x93" => "\xc3\xb3",
    "\xc3\x94" => "\xc3\xb4",  "\xc3\x95" => "\xc3\xb5",
    "\xc3\x96" => "\xc3\xb6",  "\xc3\x98" => "\xc3\xb8",
    "\xc3\x99" => "\xc3\xb9",  "\xc3\x9a" => "\xc3\xba",
    "\xc3\x9b" => "\xc3\xbb",  "\xc3\x9c" => "\xc3\xbc",
    "\xc3\x9d" => "\xc3\xbd",  "\xc3\x9e" => "\xc3\xbe",
    "\xc3\x9f" => "ss",
    ##   U+0100
    "\xc4\x80" => "\xc4\x81",  "\xc4\x82" => "\xc4\x83",
    "\xc4\x84" => "\xc4\x85",  "\xc4\x86" => "\xc4\x87",
    "\xc4\x88" => "\xc4\x89",  "\xc4\x8a" => "\xc4\x8b",
    "\xc4\x8c" => "\xc4\x8d",  "\xc4\x8e" => "\xc4\x8f",
    "\xc4\x90" => "\xc4\x91",  "\xc4\x92" => "\xc4\x93",
    "\xc4\x94" => "\xc4\x95",  "\xc4\x96" => "\xc4\x97",
    "\xc4\x98" => "\xc4\x99",  "\xc4\x9a" => "\xc4\x9b",
    "\xc4\x9c" => "\xc4\x9d",  "\xc4\x9e" => "\xc4\x9f",
    "\xc4\xa0" => "\xc4\xa1",  "\xc4\xa2" => "\xc4\xa3",
    "\xc4\xa4" => "\xc4\xa5",  "\xc4\xa6" => "\xc4\xa7",
    "\xc4\xa8" => "\xc4\xa9",  "\xc4\xaa" => "\xc4\xab",
    "\xc4\xac" => "\xc4\xad",  "\xc4\xae" => "\xc4\xaf",
    "\xc4\xb0" => "i\xcc\x87",  "\xc4\xb2" => "\xc4\xb3",
    "\xc4\xb4" => "\xc4\xb5",  "\xc4\xb6" => "\xc4\xb7",
    "\xc4\xb9" => "\xc4\xba",  "\xc4\xbb" => "\xc4\xbc",
    "\xc4\xbd" => "\xc4\xbe",  "\xc4\xbf" => "\xc5\x80",
    ##   U+0140
    "\xc5\x81" => "\xc5\x82",  "\xc5\x83" => "\xc5\x84",
    "\xc5\x85" => "\xc5\x86",  "\xc5\x87" => "\xc5\x88",
    "\xc5\x89" => "\xca\xbcn",  "\xc5\x8a" => "\xc5\x8b",
    "\xc5\x8c" => "\xc5\x8d",  "\xc5\x8e" => "\xc5\x8f",
    "\xc5\x90" => "\xc5\x91",  "\xc5\x92" => "\xc5\x93",
    "\xc5\x94" => "\xc5\x95",  "\xc5\x96" => "\xc5\x97",
    "\xc5\x98" => "\xc5\x99",  "\xc5\x9a" => "\xc5\x9b",
    "\xc5\x9c" => "\xc5\x9d",  "\xc5\x9e" => "\xc5\x9f",
    "\xc5\xa0" => "\xc5\xa1",  "\xc5\xa2" => "\xc5\xa3",
    "\xc5\xa4" => "\xc5\xa5",  "\xc5\xa6" => "\xc5\xa7",
    "\xc5\xa8" => "\xc5\xa9",  "\xc5\xaa" => "\xc5\xab",
    "\xc5\xac" => "\xc5\xad",  "\xc5\xae" => "\xc5\xaf",
    "\xc5\xb0" => "\xc5\xb1",  "\xc5\xb2" => "\xc5\xb3",
    "\xc5\xb4" => "\xc5\xb5",  "\xc5\xb6" => "\xc5\xb7",
    "\xc5\xb8" => "\xc3\xbf",  "\xc5\xb9" => "\xc5\xba",
    "\xc5\xbb" => "\xc5\xbc",  "\xc5\xbd" => "\xc5\xbe",
    "\xc5\xbf" => "s",
    ##   U+0180
    "\xc6\x81" => "\xc9\x93",  "\xc6\x82" => "\xc6\x83",
    "\xc6\x84" => "\xc6\x85",  "\xc6\x86" => "\xc9\x94",
    "\xc6\x87" => "\xc6\x88",  "\xc6\x89" => "\xc9\x96",
    "\xc6\x8a" => "\xc9\x97",  "\xc6\x8b" => "\xc6\x8c",
    "\xc6\x8e" => "\xc7\x9d",  "\xc6\x8f" => "\xc9\x99",
    "\xc6\x90" => "\xc9\x9b",  "\xc6\x91" => "\xc6\x92",
    "\xc6\x93" => "\xc9\xa0",  "\xc6\x94" => "\xc9\xa3",
    "\xc6\x96" => "\xc9\xa9",  "\xc6\x97" => "\xc9\xa8",
    "\xc6\x98" => "\xc6\x99",  "\xc6\x9c" => "\xc9\xaf",
    "\xc6\x9d" => "\xc9\xb2",  "\xc6\x9f" => "\xc9\xb5",
    "\xc6\xa0" => "\xc6\xa1",  "\xc6\xa2" => "\xc6\xa3",
    "\xc6\xa4" => "\xc6\xa5",  "\xc6\xa6" => "\xca\x80",
    "\xc6\xa7" => "\xc6\xa8",  "\xc6\xa9" => "\xca\x83",
    "\xc6\xac" => "\xc6\xad",  "\xc6\xae" => "\xca\x88",
    "\xc6\xaf" => "\xc6\xb0",  "\xc6\xb1" => "\xca\x8a",
    "\xc6\xb2" => "\xca\x8b",  "\xc6\xb3" => "\xc6\xb4",
    "\xc6\xb5" => "\xc6\xb6",  "\xc6\xb7" => "\xca\x92",
    "\xc6\xb8" => "\xc6\xb9",  "\xc6\xbc" => "\xc6\xbd",
    ##   U+01c0
    "\xc7\x84" => "\xc7\x86",  "\xc7\x85" => "\xc7\x86",
    "\xc7\x87" => "\xc7\x89",  "\xc7\x88" => "\xc7\x89",
    "\xc7\x8a" => "\xc7\x8c",  "\xc7\x8b" => "\xc7\x8c",
    "\xc7\x8d" => "\xc7\x8e",  "\xc7\x8f" => "\xc7\x90",
    "\xc7\x91" => "\xc7\x92",  "\xc7\x93" => "\xc7\x94",
    "\xc7\x95" => "\xc7\x96",  "\xc7\x97" => "\xc7\x98",
    "\xc7\x99" => "\xc7\x9a",  "\xc7\x9b" => "\xc7\x9c",
    "\xc7\x9e" => "\xc7\x9f",  "\xc7\xa0" => "\xc7\xa1",
    "\xc7\xa2" => "\xc7\xa3",  "\xc7\xa4" => "\xc7\xa5",
    "\xc7\xa6" => "\xc7\xa7",  "\xc7\xa8" => "\xc7\xa9",
    "\xc7\xaa" => "\xc7\xab",  "\xc7\xac" => "\xc7\xad",
    "\xc7\xae" => "\xc7\xaf",  "\xc7\xb0" => "j\xcc\x8c",
    "\xc7\xb1" => "\xc7\xb3",  "\xc7\xb2" => "\xc7\xb3",
    "\xc7\xb4" => "\xc7\xb5",  "\xc7\xb6" => "\xc6\x95",
    "\xc7\xb7" => "\xc6\xbf",  "\xc7\xb8" => "\xc7\xb9",
    "\xc7\xba" => "\xc7\xbb",  "\xc7\xbc" => "\xc7\xbd",
    "\xc7\xbe" => "\xc7\xbf",
    ##   U+0200
    "\xc8\x80" => "\xc8\x81",  "\xc8\x82" => "\xc8\x83",
    "\xc8\x84" => "\xc8\x85",  "\xc8\x86" => "\xc8\x87",
    "\xc8\x88" => "\xc8\x89",  "\xc8\x8a" => "\xc8\x8b",
    "\xc8\x8c" => "\xc8\x8d",  "\xc8\x8e" => "\xc8\x8f",
    "\xc8\x90" => "\xc8\x91",  "\xc8\x92" => "\xc8\x93",
    "\xc8\x94" => "\xc8\x95",  "\xc8\x96" => "\xc8\x97",
    "\xc8\x98" => "\xc8\x99",  "\xc8\x9a" => "\xc8\x9b",
    "\xc8\x9c" => "\xc8\x9d",  "\xc8\x9e" => "\xc8\x9f",
    "\xc8\xa0" => "\xc6\x9e",  "\xc8\xa2" => "\xc8\xa3",
    "\xc8\xa4" => "\xc8\xa5",  "\xc8\xa6" => "\xc8\xa7",
    "\xc8\xa8" => "\xc8\xa9",  "\xc8\xaa" => "\xc8\xab",
    "\xc8\xac" => "\xc8\xad",  "\xc8\xae" => "\xc8\xaf",
    "\xc8\xb0" => "\xc8\xb1",  "\xc8\xb2" => "\xc8\xb3",
    "\xc8\xba" => "\xe2\xb1\xa5",  "\xc8\xbb" => "\xc8\xbc",
    "\xc8\xbd" => "\xc6\x9a",  "\xc8\xbe" => "\xe2\xb1\xa6",
    ##   U+0240
    "\xc9\x81" => "\xc9\x82",  "\xc9\x83" => "\xc6\x80",
    "\xc9\x84" => "\xca\x89",  "\xc9\x85" => "\xca\x8c",
    "\xc9\x86" => "\xc9\x87",  "\xc9\x88" => "\xc9\x89",
    "\xc9\x8a" => "\xc9\x8b",  "\xc9\x8c" => "\xc9\x8d",
    "\xc9\x8e" => "\xc9\x8f",
    ##   U+0345
    "\xcd\x85" => "\xce\xb9",
    ##   U+0380
    "\xce\x86" => "\xce\xac",  "\xce\x88" => "\xce\xad",
    "\xce\x89" => "\xce\xae",  "\xce\x8a" => "\xce\xaf",
    "\xce\x8c" => "\xcf\x8c",  "\xce\x8e" => "\xcf\x8d",
    "\xce\x8f" => "\xcf\x8e",  "\xce\x90" => "\xce\xb9\xcc\x88\xcc\x81",
    "\xce\x91" => "\xce\xb1",  "\xce\x92" => "\xce\xb2",
    "\xce\x93" => "\xce\xb3",  "\xce\x94" => "\xce\xb4",
    "\xce\x95" => "\xce\xb5",  "\xce\x96" => "\xce\xb6",
    "\xce\x97" => "\xce\xb7",  "\xce\x98" => "\xce\xb8",
    "\xce\x99" => "\xce\xb9",  "\xce\x9a" => "\xce\xba",
    "\xce\x9b" => "\xce\xbb",  "\xce\x9c" => "\xce\xbc",
    "\xce\x9d" => "\xce\xbd",  "\xce\x9e" => "\xce\xbe",
    "\xce\x9f" => "\xce\xbf",  "\xce\xa0" => "\xcf\x80",
    "\xce\xa1" => "\xcf\x81",  "\xce\xa3" => "\xcf\x83",
    "\xce\xa4" => "\xcf\x84",  "\xce\xa5" => "\xcf\x85",
    "\xce\xa6" => "\xcf\x86",  "\xce\xa7" => "\xcf\x87",
    "\xce\xa8" => "\xcf\x88",  "\xce\xa9" => "\xcf\x89",
    "\xce\xaa" => "\xcf\x8a",  "\xce\xab" => "\xcf\x8b",
    "\xce\xb0" => "\xcf\x85\xcc\x88\xcc\x81",
    ##   U+03c0
    "\xcf\x82" => "\xcf\x83",  "\xcf\x90" => "\xce\xb2",
    "\xcf\x91" => "\xce\xb8",  "\xcf\x95" => "\xcf\x86",
    "\xcf\x96" => "\xcf\x80",  "\xcf\x98" => "\xcf\x99",
    "\xcf\x9a" => "\xcf\x9b",  "\xcf\x9c" => "\xcf\x9d",
    "\xcf\x9e" => "\xcf\x9f",  "\xcf\xa0" => "\xcf\xa1",
    "\xcf\xa2" => "\xcf\xa3",  "\xcf\xa4" => "\xcf\xa5",
    "\xcf\xa6" => "\xcf\xa7",  "\xcf\xa8" => "\xcf\xa9",
    "\xcf\xaa" => "\xcf\xab",  "\xcf\xac" => "\xcf\xad",
    "\xcf\xae" => "\xcf\xaf",  "\xcf\xb0" => "\xce\xba",
    "\xcf\xb1" => "\xcf\x81",  "\xcf\xb4" => "\xce\xb8",
    "\xcf\xb5" => "\xce\xb5",  "\xcf\xb7" => "\xcf\xb8",
    "\xcf\xb9" => "\xcf\xb2",  "\xcf\xba" => "\xcf\xbb",
    "\xcf\xbd" => "\xcd\xbb",  "\xcf\xbe" => "\xcd\xbc",
    "\xcf\xbf" => "\xcd\xbd",
    ##   U+0400
    "\xd0\x80" => "\xd1\x90",  "\xd0\x81" => "\xd1\x91",
    "\xd0\x82" => "\xd1\x92",  "\xd0\x83" => "\xd1\x93",
    "\xd0\x84" => "\xd1\x94",  "\xd0\x85" => "\xd1\x95",
    "\xd0\x86" => "\xd1\x96",  "\xd0\x87" => "\xd1\x97",
    "\xd0\x88" => "\xd1\x98",  "\xd0\x89" => "\xd1\x99",
    "\xd0\x8a" => "\xd1\x9a",  "\xd0\x8b" => "\xd1\x9b",
    "\xd0\x8c" => "\xd1\x9c",  "\xd0\x8d" => "\xd1\x9d",
    "\xd0\x8e" => "\xd1\x9e",  "\xd0\x8f" => "\xd1\x9f",
    "\xd0\x90" => "\xd0\xb0",  "\xd0\x91" => "\xd0\xb1",
    "\xd0\x92" => "\xd0\xb2",  "\xd0\x93" => "\xd0\xb3",
    "\xd0\x94" => "\xd0\xb4",  "\xd0\x95" => "\xd0\xb5",
    "\xd0\x96" => "\xd0\xb6",  "\xd0\x97" => "\xd0\xb7",
    "\xd0\x98" => "\xd0\xb8",  "\xd0\x99" => "\xd0\xb9",
    "\xd0\x9a" => "\xd0\xba",  "\xd0\x9b" => "\xd0\xbb",
    "\xd0\x9c" => "\xd0\xbc",  "\xd0\x9d" => "\xd0\xbd",
    "\xd0\x9e" => "\xd0\xbe",  "\xd0\x9f" => "\xd0\xbf",
    "\xd0\xa0" => "\xd1\x80",  "\xd0\xa1" => "\xd1\x81",
    "\xd0\xa2" => "\xd1\x82",  "\xd0\xa3" => "\xd1\x83",
    "\xd0\xa4" => "\xd1\x84",  "\xd0\xa5" => "\xd1\x85",
    "\xd0\xa6" => "\xd1\x86",  "\xd0\xa7" => "\xd1\x87",
    "\xd0\xa8" => "\xd1\x88",  "\xd0\xa9" => "\xd1\x89",
    "\xd0\xaa" => "\xd1\x8a",  "\xd0\xab" => "\xd1\x8b",
    "\xd0\xac" => "\xd1\x8c",  "\xd0\xad" => "\xd1\x8d",
    "\xd0\xae" => "\xd1\x8e",  "\xd0\xaf" => "\xd1\x8f",
    ##   U+0460
    "\xd1\xa0" => "\xd1\xa1",  "\xd1\xa2" => "\xd1\xa3",
    "\xd1\xa4" => "\xd1\xa5",  "\xd1\xa6" => "\xd1\xa7",
    "\xd1\xa8" => "\xd1\xa9",  "\xd1\xaa" => "\xd1\xab",
    "\xd1\xac" => "\xd1\xad",  "\xd1\xae" => "\xd1\xaf",
    "\xd1\xb0" => "\xd1\xb1",  "\xd1\xb2" => "\xd1\xb3",
    "\xd1\xb4" => "\xd1\xb5",  "\xd1\xb6" => "\xd1\xb7",
    "\xd1\xb8" => "\xd1\xb9",  "\xd1\xba" => "\xd1\xbb",
    "\xd1\xbc" => "\xd1\xbd",  "\xd1\xbe" => "\xd1\xbf",
    ##   U+0480
    "\xd2\x80" => "\xd2\x81",  "\xd2\x8a" => "\xd2\x8b",
    "\xd2\x8c" => "\xd2\x8d",  "\xd2\x8e" => "\xd2\x8f",
    "\xd2\x90" => "\xd2\x91",  "\xd2\x92" => "\xd2\x93",
    "\xd2\x94" => "\xd2\x95",  "\xd2\x96" => "\xd2\x97",
    "\xd2\x98" => "\xd2\x99",  "\xd2\x9a" => "\xd2\x9b",
    "\xd2\x9c" => "\xd2\x9d",  "\xd2\x9e" => "\xd2\x9f",
    "\xd2\xa0" => "\xd2\xa1",  "\xd2\xa2" => "\xd2\xa3",
    "\xd2\xa4" => "\xd2\xa5",  "\xd2\xa6" => "\xd2\xa7",
    "\xd2\xa8" => "\xd2\xa9",  "\xd2\xaa" => "\xd2\xab",
    "\xd2\xac" => "\xd2\xad",  "\xd2\xae" => "\xd2\xaf",
    "\xd2\xb0" => "\xd2\xb1",  "\xd2\xb2" => "\xd2\xb3",
    "\xd2\xb4" => "\xd2\xb5",  "\xd2\xb6" => "\xd2\xb7",
    "\xd2\xb8" => "\xd2\xb9",  "\xd2\xba" => "\xd2\xbb",
    "\xd2\xbc" => "\xd2\xbd",  "\xd2\xbe" => "\xd2\xbf",
    ##   U+04c0
    "\xd3\x80" => "\xd3\x8f",  "\xd3\x81" => "\xd3\x82",
    "\xd3\x83" => "\xd3\x84",  "\xd3\x85" => "\xd3\x86",
    "\xd3\x87" => "\xd3\x88",  "\xd3\x89" => "\xd3\x8a",
    "\xd3\x8b" => "\xd3\x8c",  "\xd3\x8d" => "\xd3\x8e",
    "\xd3\x90" => "\xd3\x91",  "\xd3\x92" => "\xd3\x93",
    "\xd3\x94" => "\xd3\x95",  "\xd3\x96" => "\xd3\x97",
    "\xd3\x98" => "\xd3\x99",  "\xd3\x9a" => "\xd3\x9b",
    "\xd3\x9c" => "\xd3\x9d",  "\xd3\x9e" => "\xd3\x9f",
    "\xd3\xa0" => "\xd3\xa1",  "\xd3\xa2" => "\xd3\xa3",
    "\xd3\xa4" => "\xd3\xa5",  "\xd3\xa6" => "\xd3\xa7",
    "\xd3\xa8" => "\xd3\xa9",  "\xd3\xaa" => "\xd3\xab",
    "\xd3\xac" => "\xd3\xad",  "\xd3\xae" => "\xd3\xaf",
    "\xd3\xb0" => "\xd3\xb1",  "\xd3\xb2" => "\xd3\xb3",
    "\xd3\xb4" => "\xd3\xb5",  "\xd3\xb6" => "\xd3\xb7",
    "\xd3\xb8" => "\xd3\xb9",  "\xd3\xba" => "\xd3\xbb",
    "\xd3\xbc" => "\xd3\xbd",  "\xd3\xbe" => "\xd3\xbf",
    ##   U+0500
    "\xd4\x80" => "\xd4\x81",  "\xd4\x82" => "\xd4\x83",
    "\xd4\x84" => "\xd4\x85",  "\xd4\x86" => "\xd4\x87",
    "\xd4\x88" => "\xd4\x89",  "\xd4\x8a" => "\xd4\x8b",
    "\xd4\x8c" => "\xd4\x8d",  "\xd4\x8e" => "\xd4\x8f",
    "\xd4\x90" => "\xd4\x91",  "\xd4\x92" => "\xd4\x93",
    "\xd4\xb1" => "\xd5\xa1",  "\xd4\xb2" => "\xd5\xa2",
    "\xd4\xb3" => "\xd5\xa3",  "\xd4\xb4" => "\xd5\xa4",
    "\xd4\xb5" => "\xd5\xa5",  "\xd4\xb6" => "\xd5\xa6",
    "\xd4\xb7" => "\xd5\xa7",  "\xd4\xb8" => "\xd5\xa8",
    "\xd4\xb9" => "\xd5\xa9",  "\xd4\xba" => "\xd5\xaa",
    "\xd4\xbb" => "\xd5\xab",  "\xd4\xbc" => "\xd5\xac",
    "\xd4\xbd" => "\xd5\xad",  "\xd4\xbe" => "\xd5\xae",
    "\xd4\xbf" => "\xd5\xaf",
    ##   U+0540
    "\xd5\x80" => "\xd5\xb0",  "\xd5\x81" => "\xd5\xb1",
    "\xd5\x82" => "\xd5\xb2",  "\xd5\x83" => "\xd5\xb3",
    "\xd5\x84" => "\xd5\xb4",  "\xd5\x85" => "\xd5\xb5",
    "\xd5\x86" => "\xd5\xb6",  "\xd5\x87" => "\xd5\xb7",
    "\xd5\x88" => "\xd5\xb8",  "\xd5\x89" => "\xd5\xb9",
    "\xd5\x8a" => "\xd5\xba",  "\xd5\x8b" => "\xd5\xbb",
    "\xd5\x8c" => "\xd5\xbc",  "\xd5\x8d" => "\xd5\xbd",
    "\xd5\x8e" => "\xd5\xbe",  "\xd5\x8f" => "\xd5\xbf",
    "\xd5\x90" => "\xd6\x80",  "\xd5\x91" => "\xd6\x81",
    "\xd5\x92" => "\xd6\x82",  "\xd5\x93" => "\xd6\x83",
    "\xd5\x94" => "\xd6\x84",  "\xd5\x95" => "\xd6\x85",
    "\xd5\x96" => "\xd6\x86",  "\xd6\x87" => "\xd5\xa5\xd6\x82"
  ));  
