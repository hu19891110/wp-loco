<?php
/**
 * Downgraded for PHP 5.2 compatibility. Do not edit.
 */
function loco_ensure_utf8( $str, $enc = false, $prefix_bom = false ){ if( false === $enc ){ $m = substr( $str, 0, 2 ); if( "\xEF\xBB" === $m && "\xBF" === $str{2} ){ $str = substr( $str, 3 ); } else if( "\xFF\xFE" === $m ){ $str = substr( $str, 2 ); $enc = 'UTF-16LE'; } else if( "\xFE\xFF" === $m ){ $str = substr( $str, 2 ); $enc = 'UTF-16BE'; } else { $enc = mb_detect_encoding( $str, array('UTF-8','Windows-1252','ISO-8859-1'), true ); if( ! $enc ){ throw new Exception('Unknown character encoding'); } } } else if( ! strcasecmp('ISO-8859-1',$enc) || ! strcasecmp('CP-1252', $enc ) ){ $enc = 'Windows-1252'; } else if( ! strcasecmp('UTF8', $enc) ){ $enc = ''; } else if( ! strcasecmp('UTF-16', $enc) ){ $enc = 'UTF-16BE'; } if( $enc && $enc !== 'UTF-8' ){ $str = mb_convert_encoding( $str, 'UTF-8', array($enc) ); } if( $prefix_bom ){ $str = "\xEF\xBB\xBF".$str; } return $str; }
interface LocoArrayInterface extends ArrayAccess, Iterator, Countable, JsonSerializable { public function export(); public function keys(); public function toArray(); public function getArrayCopy(); }
class LocoHeaders extends ArrayIterator implements LocoArrayInterface { private $map = array(); public function __construct( array $raw = array() ){ if( $raw ){ $keys = array_keys( $raw ); $this->map = array_combine( array_map( 'strtolower', $keys ), $keys ); parent::__construct($raw); } } public function normalize( $key ){ $k = strtolower($key); return isset($this->map[$k]) ? $this->map[$k] : null; } public function add( $key, $val ){ $this->offsetSet( $key, $val ); return $this; } public function __toString(){ $pairs = array(); foreach( $this as $key => $val ){ $pairs[] = trim($key).': '.$val; } return implode("\n", $pairs ); } public function trimmed( $prop ){ return trim( $this->__get($prop) ); } public function has( $key ){ $k = strtolower($key); return isset($this->map[$k]); } public function __get( $key ){ return $this->offsetGet( $key ); } public function __set( $key, $val ){ $this->offsetSet( $key, $val ); } public function offsetExists( $k ){ return ! is_null( $this->normalize($k) ); } public function offsetGet( $k ){ $k = $this->normalize($k); if( is_null($k) ){ return ''; } return parent::offsetGet($k); } public function offsetSet( $key, $v ){ $k = strtolower($key); if( isset($this->map[$k]) && $key !== $this->map[$k] ){ parent::offsetUnset( $this->map[$k] ); } $this->map[$k] = $key; return parent::offsetSet( $key, $v ); } public function offsetUnset( $key ){ $k = strtolower($key); if( isset($this->map[$k]) ){ parent::offsetUnset( $this->map[$k] ); unset( $this->map[$k] ); } } public function export(){ return $this->getArrayCopy(); } public function jsonSerialize(){ return $this->getArrayCopy(); } public function toArray(){ return $this->getArrayCopy(); } public function keys(){ return array_values( $this->map ); } }
class LocoPoHeaders extends LocoHeaders { public static function fromMsgstr( $str ){ $headers = new LocoPoHeaders; foreach( explode("\n",$str) as $line ){ $i = strpos($line,':') and $key = trim( substr($line,0,$i) ) and $headers->add( $key, trim( substr($line,++$i) ) ); } return $headers; } public static function fromSource( $raw ){ $raw = loco_ensure_utf8($raw); while( preg_match('/^.*[\r\n]+/u', $raw, $r ) ){ $line = $r[0]; if( '#' === $line{0} ){ $raw = substr( $raw, strlen($line) ); continue; } if( preg_match('/^msgid\s+""\s+msgstr\s+/', $raw, $r ) ){ $raw = substr( $raw, strlen($r[0]) ); $str = array(); while( preg_match('/^"(.*)"\s*/', $raw, $r ) ){ $raw = substr( $raw, strlen($r[0]) ); $chunk = $r[1]; if( '' !== $chunk ){ $str[] = stripcslashes( $r[1] ); } } if( $str ){ return self::fromMsgstr( implode('',$str) ); } break; } else { break; } } throw new Loco_error_ParseException('Invalid PO header'); } }
function loco_parse_reference_id( $refs, &$_id ){ if( false === ( $n = strpos($refs,'loco:') ) ){ $_id = ''; return $refs; } $_id = substr($refs, $n+5, 24 ); $refs = substr_replace( $refs, '', $n, 29 ); return trim( $refs ); }
function loco_parse_po( $src ){ $src = loco_ensure_utf8( $src ); $i = -1; $key = ''; $entries = array(); $template = array( '#' => array(), 'id' => array(), 'str' => array(), 'ctxt' => array() ); foreach( preg_split('/[\r\n]+/', $src) as $_i => $line ){ while( $line = trim($line," \t") ){ $c = $line{0}; if( '"' === $c ){ if( $key && isset($entry) ){ if( '"' === substr($line,-1) ){ $line = substr( $line, 1, -1 ); $entry[$key][$idx][] = stripcslashes($line); } } } else if( '#' === $c ){ if( isset($entry['i']) ){ unset( $entry ); $entry = $template; } $f = empty($line{1}) ? ' ' : $line{1}; $entry['#'][$f][] = trim( substr( $line, 1+strlen($f) ), "/ \n\r\t" ); } else if( preg_match('/^msg(id|str|ctxt|id_plural)(?:\[(\d+)\])?[ \t]+/', $line, $r ) ){ $key = $r[1]; $idx = isset($r[2]) ? (int) $r[2] : 0; if( 'str' === $key ){ if( ! isset($entry['i']) ){ $entry['i'] = ++$i; $entries[$i] = &$entry; } } else if( ! isset($entry) || isset($entry['i']) ){ unset( $entry ); $entry = $template; } $line = substr( $line, strlen($r[0]) ); continue; } continue 2; } } unset( $entry ); $assets = array(); foreach( $entries as $i => $entry ){ if( empty($entry['id']) ){ continue; } if( empty($entry['str']) ){ $entry['str'] = array( array('') ); } $asset = array( 'id' => null, 'source' => implode('',$entry['id'][0]), 'target' => implode('',$entry['str'][0]), ); if( isset($entry['ctxt'][0]) ){ $asset['context'] = implode('',$entry['ctxt'][0]); } if( isset($entry['#'][' ']) ){ $asset['comment'] = implode("\n", $entry['#'][' '] ); } if( isset($entry['#']['.']) ){ $asset['notes'] = implode("\n", $entry['#']['.'] ); } if( isset($entry['#'][':']) ){ if( $refs = implode( ' ', $entry['#'][':'] ) ) { if( $refs = loco_parse_reference_id( $refs, $_id ) ){ $asset['refs'] = $refs; } if( $_id ){ $asset['_id'] = $_id; } } } if( isset($entry['#'][',']) ){ foreach( $entry['#'][','] as $flag ){ if( preg_match('/((?:no-)?\w+)-format/', $flag, $r ) ){ $asset['format'] = $r[1]; } else if( $flag = loco_po_parse_flag($flag) ){ $asset['flag'] = $flag; break; } } } $pidx = count($assets); $assets[] = $asset; if( isset($entry['id_plural']) || isset($entry['str'][1]) ){ $idx = 0; $num = max( 2, count($entry['str']) ); while( ++$idx < $num ){ $plural = array ( 'id' => null, 'source' => '', 'target' => isset($entry['str'][$idx]) ? implode('',$entry['str'][$idx]) : '', 'plural' => $idx, 'parent' => $pidx, ); if( 1 === $idx ){ $plural['source'] = isset($entry['id_plural'][0]) ? implode('',$entry['id_plural'][0]) : ''; } $assets[] = $plural; } } } if( isset($assets[0]) && '' === $assets[0]['source'] ){ $headers = loco_parse_po_headers( $assets[0]['target'] ); $indexed = $headers['X-Loco-Lookup']; if( $indexed && 'text' !== $indexed ){ foreach( $assets as $i => $asset ){ if( isset($asset['notes']) ){ $notes = $texts = array(); foreach( explode("\n",$asset['notes']) as $line ){ 0 === strpos($line,'Source text: ') ? $texts[] = substr($line,13) : $notes[] = $line; } $assets[$i]['notes'] = implode("\n",$notes); $assets[$i]['id'] = $asset['source']; $assets[$i]['source'] = implode("\n",$texts); } } } } return $assets; }
function loco_po_parse_flag( $text ){ static $map; $flag = 0; foreach( explode(',',$text) as $needle ){ if( $needle = trim($needle) ){ if( ! isset($map) ){ $map = unserialize('a:1:{i:4;s:8:"#, fuzzy";}'); } foreach( $map as $loco_flag => $haystack ){ if( false !== stripos($haystack, $needle) ){ $flag = $loco_flag; break 2; } } } } return $flag; }
function loco_parse_po_headers( $str ){ return LocoPoHeaders::fromMsgstr($str); }
class LocoMoParser { private $bin; private $be; private $n; private $o; private $t; private $v; private $cs; public function __construct( $bin ){ $this->bin = $bin; } public function getAt( $idx ){ $offset = $this->targetOffset(); $offset += ( $idx * 8 ); $len = $this->integerAt( $offset ); $idx = $this->integerAt( $offset + 4 ); $txt = $this->bytes( $idx, $len ); if( false === strpos( $txt, "\0") ){ return $txt; } return explode( "\0", $txt ); } public function parse(){ $r = array(); $sourceOffset = $this->sourceOffset(); $targetOffset = $this->targetOffset(); $soffset = $sourceOffset; $toffset = $targetOffset; while( $soffset < $targetOffset ){ $len = $this->integerAt( $soffset ); $idx = $this->integerAt( $soffset + 4 ); $src = $this->bytes( $idx, $len ); $eot = strpos( $src, "\x04" ); if( false === $eot ){ $context = null; } else { $context = $this->decodeStr( substr($src, 0, $eot ) ); $src = substr( $src, $eot+1 ); } $sources = explode( "\0", $src, 2 ); $len = $this->integerAt( $toffset ); $idx = $this->integerAt( $toffset + 4 ); $targets = explode( "\0", $this->bytes( $idx, $len ) ); $r[] = array( 'id' => null, 'source' => $this->decodeStr( $sources[0] ), 'target' => $this->decodeStr( $targets[0] ), 'context' => $context, ); if( isset($sources[1]) ){ $p = count($r) - 1; $nforms = max( 2, count($targets) ); for( $i = 1; $i < $nforms; $i++ ){ $r[] = array( 'id' => null, 'source' => isset($sources[$i]) ? $this->decodeStr( $sources[$i] ) : sprintf('%s (plural %u)',$r[$p]['source'],$i), 'target' => isset($targets[$i]) ? $this->decodeStr( $targets[$i] ) : '', 'parent' => $p, 'plural' => $i, ); } } $soffset += 8; $toffset += 8; } return $r; } public function isBigendian(){ while( is_null($this->be) ){ $str = $this->words( 0, 1 ); if( "\xDE\x12\x04\x95" === $str ){ $this->be = false; break; } if( "\x95\x04\x12\xDE" === $str ){ $this->be = true; break; } throw new Loco_error_ParseException('Invalid MO format'); } return $this->be; } public function version(){ if( is_null($this->v) ){ $this->v = $this->integerWord(1); } return $this->v; } public function count(){ if( is_null($this->n) ){ $this->n = $this->integerWord(2); } return $this->n; } public function sourceOffset(){ if( is_null($this->o) ){ $this->o = $this->integerWord(3); } return $this->o; } public function targetOffset(){ if( is_null($this->t) ){ $this->t = $this->integerWord(4); } return $this->t; } public function getHashTable(){ $s = $this->integerWord(5); $h = $this->integerWord(6); return $this->bytes( $h, $s * 4 ); } private function bytes( $offset, $length ){ return substr( $this->bin, $offset, $length ); } private function words( $offset, $length ){ return $this->bytes( $offset * 4, $length * 4 ); } private function integerWord( $offset ){ return $this->integerAt( $offset * 4 ); } private function integerAt( $offset ){ $str = $this->bytes( $offset, 4 ); $fmt = $this->isBigendian() ? 'N' : 'V'; $arr = unpack( $fmt, $str ); if( ! isset($arr[1]) || ! is_int($arr[1]) ){ throw new Loco_error_ParseException('Failed to read integer at byte '.$offset); } return $arr[1]; } private function decodeStr( $str ){ if( $this->cs ){ $enc = $this->cs; } else { $enc = mb_detect_encoding( $str, array('ASCII','UTF-8','ISO-8859-1'), false ); if( 'ASCII' !== $enc ){ $this->cs = $enc; } } if( 'UTF-8' !== $enc ){ $str = mb_convert_encoding( $str, 'UTF-8', array($enc) ); } return $str; } }
function loco_parse_mo( $src ){ $mo = new LocoMoParser($src); return $mo->parse(); }
function loco_parse_comment($comment){ if( '*' === $comment{1} ){ $lines = array(); $junk = "\r\t/ *"; foreach( explode("\n", $comment) as $line ){ if( $line = trim($line,$junk) ){ $lines[] = trim($line,$junk); } } return implode("\n", $lines); } return trim( $comment,"/ \n\r\t" ); }
function loco_parse_wp_comment( $block ){ $header = array(); if( '*' === $block{1} ){ $junk = "\r\t/ *"; foreach( explode("\n", $block) as $line ){ if( false !== ( $i = strpos($line,':') ) ){ $key = substr($line,0,$i); $val = substr($line,++$i); $header[ trim($key,$junk) ] = trim($val,$junk); } } } return $header; }
abstract class LocoExtractor { private $rules; private $exp = array(); private $reg = array(); private $dom = array(); private $wp = array(); private $dflt = ''; abstract public function decapse( $raw ); abstract public function fsniff( $str ); public function __construct( array $rules ){ $this->rules = $rules; } public function getTotal(){ return count( $this->exp ); } public function getDomainCounts(){ return $this->dom; } public function setDomain( $default ){ $this->dflt = (string) $default; return $this; } public function headerize( array $tags, $domain = '' ){ if( isset($this->wp[$domain]) ){ $this->wp[$domain] += $tags; } else { $this->wp[$domain] = $tags; } return $this; } public function extract( LocoTokensInterface $tokens, $fileref ){ $n = 0; $comment = ''; foreach( $tokens as $tok ){ if( is_string($tok) ){ $s = $tok; $t = null; } else { $t = $tok[0]; $s = $tok[1]; if( T_WHITESPACE === $t ){ throw new RuntimeException( get_class($tokens).' should not allow whitespace through' ); } } if( isset($args) ){ if( ')' === $s ){ if( 0 === --$depth ){ if( isset($arg) ){ $args[] = $arg; } $this->push( $rule, $args, $comment, $ref ); unset($args,$arg); $comment = ''; $n++; } } else if( '(' === $s ){ $depth++; } else if( ',' === $s ){ if( isset($arg) ){ $args[] = $arg; unset($arg); } } else if( isset($arg) ){ $arg[] = $tok; } else { $arg = array( $tok ); } } else if( T_COMMENT === $t || T_DOC_COMMENT === $t ){ if( $this->wp && 0 === $n && ( $header = loco_parse_wp_comment($s) ) ){ foreach( $this->wp as $domain => $tags ){ foreach( array_intersect_key($header,$tags) as $tag => $source ){ $this->pushMeta( $source, $tags[$tag], $domain ); } } } else { $comment = $s; } } else if( T_STRING === $t && isset($this->rules[$s]) && '(' === $tokens->advance() ){ $rule = $this->rules[$s]; $args = array(); $ref = $fileref ? $fileref.':'.$tok[2]: ''; $depth = 1; } else if( $comment ){ if( false === stripos($comment, 'translators:') && false === strpos($comment, 'xgettext:') ){ $comment = ''; } } } return $this->exp; } public function pushMeta( $source, $notes = '', $domain = null ){ if( ! $domain ){ $domain = $this->dflt; } $entry = array( 'id' => '', 'source' => $source, 'target' => '', 'notes' => $notes, ); if( $domain ){ $entry['domain'] = $domain; $key = $source."\1".$domain; } else { $key = $source; } $this->pushMsgid( $key, $entry, $domain ); return $this; } private function pushMsgid( $key, array $entry, $domain ){ if( isset($this->reg[$key]) ){ $index = $this->reg[$key]; $a = array(); isset($this->exp[$index]['refs']) and $a[] = $this->exp[$index]['refs']; isset($entry['refs']) and $a[] = $entry['refs']; $a && $this->exp[$index]['refs'] = implode(" ", $a ); $a = array(); isset($this->exp[$index]['notes']) and $a[] = $this->exp[$index]['notes']; isset($entry['notes']) and $a[] = $entry['notes']; $a && $this->exp[$index]['notes'] = implode("\n", $a ); } else { $index = count($this->exp); $this->reg[$key] = $index; $this->exp[] = $entry; if( isset($this->dom[$domain]) ){ $this->dom[$domain]++; } else { $this->dom[$domain] = 1; } } return $index; } private function push( $rule, array $args, $comment = '', $ref = '' ){ $s = strpos( $rule, 's'); $p = strpos( $rule, 'p'); $c = strpos( $rule, 'c'); $d = strpos( $rule, 'd'); foreach( $args as $i => $tokens ){ if( 1 === count($tokens) && is_array($tokens[0]) && T_CONSTANT_ENCAPSED_STRING === $tokens[0][0] ){ $args[$i] = $this->decapse( $tokens[0][1] ); } else { $args[$i] = null; } } if( ! isset($args[$s]) ){ return null; } $key = $msgid = $args[$s]; if( ! is_string($msgid) ){ return null; } $entry = array( 'id' => '', 'source' => $msgid, 'target' => '', ); if( is_int($c) && isset($args[$c]) ){ $entry['context'] = $context = $args[$c]; $key .= "\0". $context; } else if( ! isset($msgid{0}) ){ return null; } else { $context = null; } if( $ref ){ $entry['refs'] = $ref; } if( is_int($d) && array_key_exists($d,$args) ){ $domain = $args[$d]; if( is_null($domain) ){ $domain = ''; } } else { $domain = $this->dflt; } if( $domain ){ $entry['domain'] = $domain; $key .= "\1".$domain; } $parse_printf = true; if( $comment ){ if( preg_match('/xgettext:\s*((?:no-)?\w+)-format/', $comment, $r ) ){ $entry['format'] = $r[1]; if( 'no-' === substr($r[1],0,3) ){ $parse_printf = false; } else { $parse_printf = null; } $comment = str_replace( $r[0], '', $comment ); } $comment = loco_parse_comment($comment); if( preg_match('/^translators:\s+/i', $comment, $r ) ){ $comment = substr( $comment, strlen($r[0]) ); } $entry['notes'] = $comment; } if( $parse_printf && ( $format = $this->fsniff($msgid) ) ){ $entry['format'] = $format; } $index = $this->pushMsgid( $key, $entry, $domain ); if( is_int($p) && isset($args[$p]) ){ $msgid_plural = $args[$p]; $entry = array( 'id' => '', 'source' => $msgid_plural, 'target' => '', 'plural' => 1, 'parent' => $index, ); if( false !== $parse_printf && ( $format = $this->fsniff($msgid_plural) ) ){ $entry['format'] = $format; } $pkey = $key."\2"; if( isset($this->reg[$pkey]) ){ $plural_index = $this->reg[$pkey]; $this->exp[$plural_index] = $entry; } else { $plural_index = count($this->exp); $this->reg[$pkey] = $plural_index; $this->exp[] = $entry; } } return $index; } public function filter( $domain ){ $map = array(); $newOffset = 1; $matchAll = '*' === $domain; $raw = array( array( 'id' => '', 'source' => '', 'target' => '', 'domain' => $matchAll ? '' : $domain, ) ); foreach( $this->exp as $oldOffset => $r ){ if( isset($r['parent']) ){ if( isset($map[$r['parent']]) ){ $r['parent'] = $map[ $r['parent'] ]; $raw[ $newOffset++ ] = $r; } } else { if( $matchAll ){ $match = true; } else if( isset($r['domain']) ){ $match = $domain === $r['domain']; } else { $match = $domain === ''; } if( $match ){ $map[ $oldOffset ] = $newOffset; $raw[ $newOffset++ ] = $r; } } } return $raw; } }
interface LocoTokensInterface extends Iterator { public function advance(); }
class LocoPHPTokens implements LocoTokensInterface { private $i; private $tokens; private $skip_tokens = array(); private $skip_strings = array(); private $literal_tokens = array(); public function __construct( array $tokens ){ $this->tokens = $tokens; $this->rewind(); } public function literal(){ foreach( func_get_args() as $t ){ $this->literal_tokens[ $t ] = 1; } return $this; } public function ignore(){ foreach( func_get_args() as $t ){ if( is_int($t) ){ $this->skip_tokens[$t] = true; } else { $this->skip_strings[$t] = true; } } return $this; } public function export(){ $arr = array(); foreach( $this as $tok ){ $arr[] = $tok; } return $arr; } public function advance(){ $this->next(); return $this->current(); } public function pop(){ $tok = array_pop( $this->tokens ); $this->rewind(); return $tok; } public function shift(){ $tok = array_shift( $this->tokens); $this->rewind(); return $tok; } public function rewind(){ $this->i = ( false === reset($this->tokens) ? null : key($this->tokens) ); } public function valid(){ while( isset($this->i) ){ $tok = $this->tokens[$this->i]; if( is_array($tok) ){ if( isset($this->skip_tokens[$tok[0]]) ){ $this->next(); } else { return true; } } else if( isset($this->skip_strings[$tok]) ){ $this->next(); } else { return true; } } return false; } public function key(){ return $this->i; } public function next(){ $this->i = ( false === next($this->tokens) ? null : key($this->tokens) ); } public function current(){ if( ! $this->valid() ){ return false; } $tok = $this->tokens[$this->i]; if( is_array($tok) && isset($this->literal_tokens[$tok[0]]) ){ return $tok[1]; } return $tok; } public function __toString(){ $s = ''; foreach( $this as $token ){ $s .= is_array($token) ? $token[1] : $token; } return $s; } }
function loco_sniff_printf( $s, $p, $limit = 0 ){ $n = 0; while( $s && false !== ( $i = strpos($s,'%') ) ){ if( 0 !== $i ){ $s = substr( $s, $i ); } if( preg_match( $p, $s, $r ) ){ $s = substr( $s, strlen($r[0]) ); if( ++$n === $limit ){ break; } } else { return 0; } } return $n; }
function loco_sniff_php_printf( $s, $limit = 0 ){ return loco_sniff_printf( $s, '/^%(?:\\d+\\$)?[-+]?(?:\'.)?[ 0]*-?\\d*(?:\\.\\d+)?[suxXbcdeEfFgGo%]/', $limit ); }
function loco_decapse_php_string( $s ){ if( ! $s ){ return ''; } $q = $s{0}; if( "'" === $q ){ return str_replace( array( '\\'.$q, '\\\\' ), array( $q, '\\' ), substr( $s, 1, -1 ) ); } if( '"' !== $q ){ return $s; } $s = substr( $s, 1, -1 ); $a = ''; $e = false; $symbols = array ( 'n' => "\x0A", 'r' => "\x0D", 't' => "\x09", 'v' => "\x0B", 'f' => "\x0C", 'e' => "\x1B", '$' => '$', '\\' => '\\', '"' => '"', ); foreach( explode('\\', $s) as $i => $t ){ if( '' === $t ){ if( $e ){ $a .= '\\'; } $e = ! $e; continue; } if( $e ){ $c = $t{0}; while( true ){ if( 'x' === $c || 'X' === $c ){ if( preg_match('/^x([0-9a-f]{1,2})/i', $t, $n ) ){ $c = chr( intval( $n[1], 16 ) ); $n = strlen($n[0]); break; } } else if( isset($symbols[$c]) ){ $c = $symbols[$c]; $n = 1; break; } else if( preg_match('/^[0-7]{1,3}/', $t, $n ) ){ $c = chr( intval( $n[0], 8 ) ); $n = strlen($n[0]); break; } $a .= '\\'.$t; continue 2; } $a .= substr_replace( $t, $c, 0, $n ); continue; } $a .= $t; $e = true; } return $a; }
final class LocoPHPExtractor extends LocoExtractor { public function extractSource( $src, $fileref = '' ){ $tokens = new LocoPHPTokens( token_get_all($src) ); $tokens->ignore( T_WHITESPACE ); return $this->extract( $tokens, $fileref ); } public function decapse( $raw ){ return loco_decapse_php_string( $raw ); } public function fsniff( $str ){ return loco_sniff_php_printf($str) ? 'php' : ''; } }
function loco_wp_extractor(){ $e = new LocoPHPExtractor( array( '__' => 'sd', '_e' => 'sd', '_c' => 'sd', '_n' => 'sp_d', '_n_noop' => 'spd', '_nc' => 'sp_d', '__ngettext' => 'spd', '__ngettext_noop' => 'spd', '_x' => 'scd', '_ex' => 'scd', '_nx' => 'sp_cd', '_nx_noop' => 'spcd', 'esc_attr__' => 'sd', 'esc_html__' => 'sd', 'esc_attr_e' => 'sd', 'esc_html_e' => 'sd', 'esc_attr_x' => 'scd', 'esc_html_x' => 'scd', ) ); return $e->setDomain('default'); }
abstract class LocoPo { public static function pair( $key, $text, $width = 79 ){ if( ! $text && '0' !== $text ){ return $key.' ""'; } $text = addcslashes( $text, "\t\x0B\x0C\x07\x08\\\"" ); $text = preg_replace('/\R/u', "\\n\n", $text, -1, $nbr ); if( $nbr ){ } else if( $width && $width < mb_strlen($text,'UTF-8') + strlen($key) + 3 ){ } else { return $key.' "'.$text.'"'; } $lines = array( $key.' "' ); if( $width ){ $width -= 2; $a = '/^.{0,'.($width-1).'}[-– \\.,:;\\?!\\)\\]\\}\\>]/u'; $b = '/^[^-– \\.,:;\\?!\\)\\]\\}\\>]+/u'; foreach( explode("\n",$text) as $unwrapped ){ $length = mb_strlen( $unwrapped, 'UTF-8' ); while( $length > $width ){ if( preg_match( $a, $unwrapped, $r ) ){ $line = $r[0]; } else if( preg_match( $b, $unwrapped, $r ) ){ $line = $r[0]; } else { throw new Exception('Wrapping error'); } $lines[] = $line; $trunc = mb_strlen($line,'UTF-8'); $length -= $trunc; $unwrapped = substr( $unwrapped, strlen($line) ); if( ( false === $unwrapped && 0 !== $length ) || ( 0 === $length && false !== $unwrapped ) ){ throw new Exception('Truncation error'); } } if( 0 !== $length ){ $lines[] = $unwrapped; } } } else { foreach( explode("\n",$text) as $unwrapped ){ $lines[] = $unwrapped; } } return implode("\"\n\"",$lines).'"'; } public static function refs( $text, $width = 76 ){ $text = preg_replace('/\s+/', ' ', $text ); return '#: '.wordwrap( $text, $width, "\n#: ", false ); } public static function prefix( $text, $prefix ){ $lines = preg_split('/\\R/u', $text, -1 ); return $prefix.implode( "\n".$prefix, $lines ); } }
class LocoPoIterator implements Iterator { private $po; private $headers; private $i; private $t; private $j; private $z; private $m; public function __construct( $po ){ $this->po = $po; $this->t = count( $po ); if( ! isset($po[0]) ){ throw new InvalidArgumentException('Empty PO data'); } $h = $po[0]; if( '' === $h['source'] && empty($h['context']) ){ $this->z = 0; } else { $this->z = -1; } } public function rewind(){ $this->i = $this->z; $this->j = -1; $this->next(); } public function key(){ return $this->j; } public function valid(){ return is_int($this->i); } public function next(){ $i = $this->i; while( ++$i < $this->t ){ $this->j++; $this->i = $i; return; } $this->i = null; $this->j = null; } public function current(){ $i = $this->i; $po = $this->po; $parent = new LocoPoMessage( $po[$i] ); $plurals = array(); while( isset($po[++$i]['parent']) ){ $this->i = $i; $plurals[] = new LocoPoMessage( $po[$i] ); } if( $plurals ){ $parent['plurals'] = $plurals; } return $parent; } public function getArrayCopy(){ $po = $this->po; if( 0 === $this->z ){ $po[0]['target'] = (string) $this->getHeaders(); } return $po; } public function getHeaders(){ if( ! $this->headers ){ $header = $this->po[0]; if( 0 === $this->z ){ $this->headers = loco_parse_po_headers( $header['target'] ); } else { $this->headers = new LocoPoHeaders; } } return $this->headers; } public function initPo(){ if( 0 === $this->z ){ unset( $this->po[0]['flag'] ); } return $this; } public function initPot(){ if( 0 === $this->z ){ $this->po[0]['flag'] = 4; } return $this; } public function strip(){ $po = $this->po; $i = count($po); $z = $this->z; while( --$i > $z ){ $po[$i]['target'] = ''; } $this->po = $po; return $this; } public function __toString(){ try { if( 0 === $this->z ){ $h = $this->po[0]; } else { $h = array( 'source' => '' ); } $h['target'] = (string) $this->getHeaders(); $msg = new LocoPoMessage( $h ); $s = $msg->__toString(); foreach( $this as $msg ){ $s .= "\n".$msg->__toString(); } } catch( Exception $e ){ trigger_error( $e->getMessage(), E_USER_WARNING ); $s = ''; } return $s; } public function getHashes(){ $a = array(); foreach( $this as $msg ){ $a[] = $msg->getHash(); } sort( $a, SORT_STRING ); return $a; } public function equalSource( LocoPoIterator $that ){ $a = $this->getHashes(); $b = $that->getHashes(); if( count($a) !== count($b) ){ return false; } foreach( $a as $i => $hash ){ if( $hash !== $b[$i] ){ return false; } } return true; } }
class LocoPoMessage extends ArrayObject { public function __construct( array $r ){ $r['key'] = $r['source']; parent::__construct($r); } public function __get( $prop ){ return isset($this[$prop]) ? $this[$prop] : null; } private function _getFlags(){ $flags = array(); $plurals = $this->__get('plurals'); if( 4 === $this->__get('flag') ){ $flags[] = 'fuzzy'; } else if( $plurals ){ foreach( $plurals as $child ){ if( 4 === $child->__get('flag') ){ $flags[] = 'fuzzy'; break; } } } if( $f = $this->__get('format') ){ $flags[] = $f.'-format'; } else if( isset($plurals[0]) && ( $f = $plurals[0]->__get('format') ) ){ $flags[] = $f.'-format'; } return $flags; } public function getHash(){ $msgid = $this['source']; if( isset($this['context']) ){ $msgctxt = $this['context']; if( is_string($msgctxt) && '' !== $msgctxt ){ if( ! $msgid && '0' !== $msgid ){ $msgid = '('.$msgctxt.')'; } $msgid = $msgctxt."\x04".$msgid; } } if( isset($this['plurals']) ){ foreach( $this['plurals'] as $p ){ $msgid .= "\0".$p->getHash(); break; } } return $msgid; } public function __toString(){ $s = ''; try { if( $text = $this->__get('comment') ) { $s .= LocoPo::prefix( $text, '# ')."\n"; } if( $text = $this->__get('notes') ) { $s .= LocoPo::prefix( $text, '#. ')."\n"; } if( $text = $this->__get('refs') ){ $s .= LocoPo::refs( $text )."\n"; } if( $texts = $this->_getFlags() ){ $s .= '#, '.implode(', ',$texts)."\n"; } $text = $this->__get('context'); if( is_string($text) && isset($text{0}) ){ $s .= LocoPo::pair('msgctxt', $text )."\n"; } $s .= LocoPo::pair( 'msgid', $this['key'] )."\n"; $target = $this['target']; if( is_array( $plurals = $this->__get('plurals') ) ){ if( $plurals ){ foreach( $plurals as $i => $p ){ if( 0 === $i ){ $s .= LocoPo::pair('msgid_plural', $p['key'])."\n"; $s .= LocoPo::pair('msgstr[0]', $target)."\n"; } $s .= LocoPo::pair('msgstr['.(++$i).']', $p['target'])."\n"; } } else if( isset($this['plural_key']) ){ $s .= LocoPo::pair('msgid_plural', $this['plural_key'] )."\n"; $s .= LocoPo::pair('msgstr[0]', $target)."\n"; } else { trigger_error('Missing plural_key in zero plural export'); $s .= LocoPo::pair('msgstr', $target )."\n"; } } else { $s .= LocoPo::pair('msgstr', $target )."\n"; } } catch( Exception $e ){ trigger_error( $e->getMessage(), E_USER_WARNING ); } return $s; } }
class LocoMoTable { private $size = 0; private $bin = ''; private $map; public function __construct( $data = null ){ if( is_array($data) ){ $this->compile( $data ); } else if( $data ){ $this->parse( $data ); } } public function count(){ if( ! isset($this->size) ){ if( $this->bin ){ $this->size = (int) ( strlen( $this->bin ) / 4 ); } else if( is_array($this->map) ){ $this->size = count($this->map); } else { return 0; } if( ! self::is_prime($this->size) || $this->size < 3 ){ throw new Exception('Size expected to be prime number above 2, got '.$this->size); } } return $this->size; } public function bytes(){ return $this->count() * 4; } public function __toString(){ return $this->bin; } public function export(){ if( ! is_array($this->map) ){ $this->parse( $this->bin ); } return $this->map; } private function reset( $length ){ $this->size = max( 3, self::next_prime ( $length * 4 / 3 ) ); $this->bin = null; $this->map = array(); return $this->size; } public function compile( array $msgids ){ $hash_tab_size = $this->reset( count($msgids) ); $packed = array_fill( 0, $hash_tab_size, "\0\0\0\0" ); $j = 0; foreach( $msgids as $msgid ){ $hash_val = self::hashpjw( $msgid ); $idx = $hash_val % $hash_tab_size; if( array_key_exists($idx, $this->map) ){ $incr = 1 + ( $hash_val % ( $hash_tab_size - 2 ) ); do { $idx += $incr; if( $hash_val === $idx ){ throw new Exception('Unable to find empty slot in hash table'); } $idx %= $hash_tab_size; } while( array_key_exists($idx, $this->map ) ); } $this->map[$idx] = $j; $packed[$idx] = pack('V', ++$j ); } return $this->bin = implode('',$packed); } public function lookup( $msgid, array $msgids ){ $hash_val = self::hashpjw( $msgid ); $idx = $hash_val % $this->size; $incr = 1 + ( $hash_val % ( $this->size - 2 ) ); while( true ){ if( ! array_key_exists($idx, $this->map) ){ break; } $j = $this->map[$idx]; if( isset($msgids[$j]) && $msgid === $msgids[$j] ){ return $j; } $idx += $incr; if( $idx === $hash_val ){ break; } $idx %= $this->size; } return -1; } public function parse( $bin ){ $this->bin = (string) $bin; $this->size = null; $hash_tab_size = $this->count(); $this->map = array(); $idx = -1; $byte = 0; while( ++$idx < $hash_tab_size ){ $word = substr( $this->bin, $byte, 4 ); if( "\0\0\0\0" !== $word ){ list(,$j) = unpack('V', $word ); $this->map[$idx] = $j - 1; } $byte += 4; } return $this->map; } public static function hashpjw( $str ){ $i = -1; $hval = 0; $len = strlen($str); while( ++$i < $len ){ $ord = ord( $str{$i} ); $hval = ( $hval << 4 ) + $ord; $g = $hval & 0xf0000000; if( $g !== 0 ){ $hval ^= $g >> 24; $hval ^= $g; } } return $hval; } private static function next_prime( $seed ){ $seed |= 1; while ( ! self::is_prime($seed) ){ $seed += 2; } return $seed; } private static function is_prime( $num ) { if ($num === 1 ){ return false; } if( $num === 2 ){ return true; } if( $num % 2 == 0 ) { return false; } for( $i = 3; $i <= ceil(sqrt($num)); $i = $i + 2) { if($num % $i == 0 ){ return false; } } return true; } }
class LocoMo { private $bin; private $msgs; private $head; private $hash; private $use_fuzzy = false; public function __construct( Iterator $export, Iterator $head = null ){ if( $head ){ $this->head = $head; } else { $this->head = new LocoHeaders( array ( 'Project-Id-Version' => 'Loco', 'Language' => 'English', 'Plural-Forms' => 'nplurals=2; plural=(n!=1);', 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => '8bit', 'X-Generator' => 'Loco '.PLUG_HTTP_ADDR, ) ); } $this->msgs = $export; $this->bin = ''; } public function enableHash(){ return $this->hash = new LocoMoTable; } public function useFuzzy(){ $this->use_fuzzy = true; } public function setHeader( $key, $val ){ $this->head->add($key, $val); return $this; } public function setProject( LocoProject $Proj ){ return $this ->setHeader( 'Project-Id-Version', $Proj->proj_name ) ->setHeader($key, $val) ; } public function setLocale( LocoProjectLocale $Loc ){ return $this ->setHeader( 'Language', $Loc->label ) ->setHeader( 'Plural-Forms', (string) $Loc->getPlurals() ) ; } public function count(){ return count($this->msgs); } public function compile(){ $table = array(''); $sources = array(''); $targets = array( (string) $this->head ); $fuzzy_flag = 4; $skip_fuzzy = ! $this->use_fuzzy; foreach( $this->msgs as $r ){ if( isset($r['flag']) && $skip_fuzzy && $fuzzy_flag === $r['flag'] ){ continue; } $msgid = $r['key']; if( isset($r['context']) ){ $msgctxt = $r['context']; if( is_string($msgctxt) && '' !== $msgctxt ){ if( ! $msgid && '0' !== $msgid ){ $msgid = '('.$msgctxt.')'; } $msgid = $msgctxt."\x04".$msgid; } } if( ! $msgid && '0' !== $msgid ){ continue; } $msgstr = $r['target']; if( ! $msgstr && '0' !== $msgstr ){ continue; } $table[] = $msgid; if( isset($r['plurals']) ){ foreach( $r['plurals'] as $i => $p ){ if( $i === 0 ){ $msgid .= "\0".$p['key']; } $msgstr .= "\0".$p['target']; } } $sources[] = $msgid; $targets[] = $msgstr; } asort( $sources, SORT_STRING ); $this->bin = "\xDE\x12\x04\x95\x00\x00\x00\x00"; $n = count($sources); $this->writeInteger( $n ); $offset = 28; $this->writeInteger( $offset ); $offset += $n * 8; $this->writeInteger( $offset ); if( $this->hash ){ sort( $table, SORT_STRING ); $this->hash->compile( $table ); $s = $this->hash->count(); } else { $s = 0; } $this->writeInteger( $s ); $offset += $n * 8; $this->writeInteger( $offset ); if( $s ){ $offset += $s * 4; } $source = ''; foreach( $sources as $i => $str ){ $source .= $str."\0"; $this->writeInteger( $strlen = strlen($str) ); $this->writeInteger( $offset ); $offset += $strlen + 1; } $target = ''; foreach( array_keys($sources) as $i ){ $str = $targets[$i]; $target .= $str."\0"; $this->writeInteger( $strlen = strlen($str) ); $this->writeInteger( $offset ); $offset += $strlen + 1; } if( $this->hash ){ $this->bin .= $this->hash->__toString(); } $this->bin .= $source; $this->bin .= $target; return $this->bin; } private function writeInteger( $num ){ $this->bin .= pack( 'V', $num ); return $this; } }
function loco_print_percent( $n, $t ){ $s = loco_string_percent( (int) $n, (int) $t ); echo $s,'%'; return ''; }
function loco_print_progress( $translated, $untranslated, $flagged ){ $total = $translated + $untranslated; $complete = loco_string_percent( $translated - $flagged, $total ); $class = 'progress'; if( ! $translated && ! $flagged ){ $class .= ' empty'; } echo '<div class="',$class,'"><div class="t">'; if( $flagged ){ $s = loco_string_percent( $flagged, $total ); echo '<div class="bar f" style="width:',$s,'%">&nbsp;</div>'; } if( '0' === $complete ){ echo '&nbsp;'; } else { $class = 'bar p'; $p = (int) $complete; $class .= sprintf(' p-%u', 10*floor($p/10) ); $style = 'width:'.$complete.'%'; if( $flagged ){ $remain = 100.0 - (float) $s; $style .= '; max-width: '.sprintf('%s',$remain).'%'; } echo '<div class="',$class,'" style="'.$style.'">&nbsp;</div>'; } echo '</div><div class="l">',$complete,'%</div></div>'; return ''; }
function loco_string_percent( $n, $t ){ if( ! $t || ! $n ){ $s = '0'; } else if( $t === $n ){ $s = '100'; } else { $dp = 0; $n = 100 * $n / $t; if( $n > 99 ){ $n = min( $n, 99.99 ); do { $s = number_format( $n, ++$dp ); } while( '100' === substr($s,0,3) && $dp < 2 ); } else if( $n < 0.5 ){ $n = max( $n, 0.0001 ); do { $s = number_format( $n, ++$dp ); } while( preg_match('/^0\\.0+$/',$s) && $dp < 4 ); } else { $s = number_format( $n, $dp ); } } return $s; }
