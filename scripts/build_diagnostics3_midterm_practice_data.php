<?php
declare(strict_types=1);
// Convert reviewed owner-authored Drive HTML exams to the site's native question contract.
// Read-only source: staged Google Drive HTML; 13/14 deliberately excluded for topic mismatch.
$sessions=[
1=>['ضایعات اگزوفیتیک خارج‌استخوانی ۱',35,'1kBRQgMsqVUZzGoFKozU4WaOs-QULvbxE'],
2=>['ضایعات اگزوفیتیک خارج‌استخوانی ۲',35,'1jFgMd3FeZG-QF2nRF5jCJghgsbWyV_gJ'],
3=>['ضایعات اگزوفیتیک خارج‌استخوانی ۳',35,'1QCQiZ_8NIOS1zPYAIoNk3tktmiI3U995'],
4=>['ضایعات واکنشی ۱',35,'1QFgyO0pvL3k_MJViBJqxS5rKrxWOcmqa'],
5=>['ضایعات واکنشی ۲',40,'1dGPMqocONRQ6uVjM6lUFgnAzX_BhMm2W'],
6=>['ضایعات واکنشی ۳',35,'1A0jBAPgkma3vPl0Wi1PadpKnp3qNKjiI'],
8=>['ضایعات سفید و قرمز ۱',40,'1RQgPRyRXLWzL6T7SNPruTObzsUTAD7Me'],
9=>['ضایعات سفید و قرمز ۲؛ کاندیدا و لیکن‌پلان',40,'1Xe98pJZG2vrPGeJy097z-q2To4nmXUN2'],
10=>['ضایعات سفید و قرمز ۳',35,'1Hoo_4H7iNphZXYFbcscHmMg4OlRDDUed'],
12=>['ضایعات خوش‌خیم اپیتلیالی ۱',35,'1kGqAxmk65BsvlnBlPvLb5pCNTs18artE'],
15=>['ضایعات پیگمانته ۱',40,'1LDo5bqcNE4-Jp8CeQGEJv39HzU39yRjg'],
16=>['ضایعات پیگمانته ۲',40,'1uSgtHk9d_AyeoNTyIEI2LWL-hq9QZXKT'],
17=>['ضایعات پیگمانته ۳',35,'1-BL57vyu03T79o3MO33wapjHy0xRakFz'],
];
function dig3_t(string $s):string{return trim((string)preg_replace('/\s+/u',' ',$s));}
function dig3_cls(DOMXPath $x,DOMNode $n,string $c):DOMNodeList{return $x->query('.//*[contains(concat(" ",normalize-space(@class)," ")," '.$c.' ")]',$n);}
function dig3_find(DOMXPath $x,DOMNode $n,array $names):?DOMNode{foreach($names as $c){$v=dig3_cls($x,$n,$c);if($v->length)return $v->item(0);}return null;}
function dig3_opt(DOMXPath $x,DOMNode $n):string{
 $s=dig3_t($n->textContent);$l=dig3_find($x,$n,['letter','op-letter','optletter']);
 if($l){$p=dig3_t($l->textContent);if(str_starts_with($s,$p))$s=dig3_t(substr($s,strlen($p)));}
 return dig3_t((string)preg_replace('/^(?:الف|ا|ب|ج|د)\s*[\)\.:\-]\s*/u','',$s));
}
function dig3_parse(string $html,int $session,string $topic,int $expected,string $id):array{
 $html=(string)preg_replace('~<style\b[^>]*>.*?</style>~is','',$html);
 $d=new DOMDocument();libxml_use_internal_errors(true);
 if(!$d->loadHTML('<?xml encoding="UTF-8"?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))throw new RuntimeException("Malformed HTML {$session}");
 libxml_clear_errors();$x=new DOMXPath($d);
 $q=$x->query('//*[contains(concat(" ",normalize-space(@class)," ")," question ") or contains(concat(" ",normalize-space(@class)," ")," q ") or contains(concat(" ",normalize-space(@class)," ")," qitem ")]');
 $a=$x->query('//*[contains(concat(" ",normalize-space(@class)," ")," answer ") or contains(concat(" ",normalize-space(@class)," ")," aitem ") or contains(concat(" ",normalize-space(@class)," ")," ans ")]');
 if($q->length!==$expected||$a->length!==$expected)throw new RuntimeException("Session {$session}: expected {$expected}, got {$q->length} questions {$a->length} answers");
 $result=[];$seen=[];
 foreach($q as $i=>$n){
  $s=dig3_find($x,$n,['q-stem','stem','qtext']);
  if(!$s){$p=$x->query('.//p',$n);$s=$p->length?$p->item(0):null;}
  $stem=dig3_t($s?$s->textContent:'');
  $stem=dig3_t((string)preg_replace('/^(?:س(?:ؤ|و)ال\s*)?[۰-۹٠-٩0-9]+\s*[\.\)\-:]\s*/u','',$stem));
  if($session===17)$stem=dig3_t((string)preg_replace('/^[0-9]{1,2}(?=در\s)/u','',$stem));
  $li=$x->query('.//li',$n);$opts=[];
  foreach(($li->length===4?$li:dig3_cls($x,$n,'opt')) as $o)$opts[]=dig3_opt($x,$o);
  if(mb_strlen($stem)<10||count($opts)!==4||count(array_unique($opts))!==4)throw new RuntimeException("Bad question {$session}/".($i+1));
  foreach($opts as $option)if(mb_strlen($option)<1)throw new RuntimeException("Empty option {$session}/".($i+1));
  $norm=mb_strtolower((string)preg_replace('/[\s\x{200C}]+/u','',$stem));
  if(isset($seen[$norm]))throw new RuntimeException("Repeated question {$session}/".($i+1));
  $seen[$norm]=true;
  $result[]=['question'=>$stem,'options'=>$opts,'topic'=>$topic];
 }
 foreach($a as $i=>$n){
  $text=dig3_t($n->textContent);
  if(!preg_match('/گزینه[ٔ‌ ]*\s*(الف|ا|ب|ج|د)/u',$text,$m))throw new RuntimeException("Answer key missing {$session}/".($i+1));
  $key=['الف'=>0,'ا'=>0,'ب'=>1,'ج'=>2,'د'=>3][$m[1]];
  $num=$n instanceof DOMElement?($n->getAttribute('data-answer')?:$n->getAttribute('id')):'';
  if(preg_match('/(\d+)$/',$num,$m)&&intval($m[1])!==$i+1)throw new RuntimeException("Answer number mismatch {$session}/".($i+1));
  $ps=$x->query('.//p',$n);$parts=[];
  foreach($ps as $p)$parts[]=dig3_t($p->textContent);
  if(!$parts){$v=dig3_find($x,$n,['why','explain','exp']);if($v)$parts[]=dig3_t($v->textContent);}
  $why=dig3_t(implode(' ',$parts));if(mb_strlen($why)<8)throw new RuntimeException("Explanation missing {$session}/".($i+1));
  $ref=dig3_find($x,$n,['reference','source','ref','page']);
  if(!$ref){$sm=$x->query('.//small',$n);$ref=$sm->length?$sm->item(0):null;}
  $result[$i]['correctIndex']=$key;$result[$i]['explanation']=$why;
  if($ref&&dig3_t($ref->textContent)!=='')$result[$i]['reference']=dig3_t($ref->textContent);
 }
 return ['session'=>$session,'topic'=>$topic,'sourceDriveId'=>$id,'questionCount'=>count($result),'questions'=>$result];
}
$root=$argv[1]??'/var/lib/sentinelx/uploads/dent-diagnostic3-20261117';
$out=__DIR__.'/../public_html/api/data/diagnostics3_midterm_practice';
if(!is_dir($out)&&!mkdir($out,0755,true)&&!is_dir($out))throw new RuntimeException('Cannot create data dir');
$total=0;
foreach($sessions as $n=>[$topic,$expected,$driveId]){
 $source=$root.'/session-'.$n.'.html';if(!is_file($source))throw new RuntimeException("Missing source {$n}");
 $data=dig3_parse((string)file_get_contents($source),$n,$topic,$expected,$driveId);
 $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
 if(file_put_contents($out.'/session-'.$n.'.txt',$json."\n")===false)throw new RuntimeException("Cannot write {$n}");
 $total+=$expected;echo "PASS session {$n}: {$expected}\n";
}
echo "PASS ".count($sessions)." sessions; {$total} questions; 7/11 absent, 13/14 held for mismatched topics.\n";
