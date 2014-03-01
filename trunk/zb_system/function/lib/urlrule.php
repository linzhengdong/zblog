<?php
/**
 * Z-Blog with PHP
 * @author
 * @copyright (C) RainbowSoft Studio
 * @version 2.0 2013-06-14
 */

/**
* UrlRule
*/
class UrlRule
{
	public $Rules=array();
	public $Url='';
	private $PreUrl='';
	public $MakeReplace=true;

	public function __construct($url){
		$this->PreUrl=$url;
	}

	private function Make_Preg(){
		global $zbp;

		$this->Rules['{%host%}']=$zbp->host;
		if(isset($this->Rules['{%page%}'])){
			if($this->Rules['{%page%}']=='1'||$this->Rules['{%page%}']=='0'){$this->Rules['{%page%}']='';}
		}
		$s=$this->PreUrl;
		foreach ($this->Rules as $key => $value) {
			$s=preg_replace($key, $value, $s);
		}
		$s=preg_replace('/\{[\?\/&a-z0-9]*=\}/', '', $s);
		$s=preg_replace('/\{\/?}/', '', $s);
		$s=str_replace(array('{','}'), array('',''), $s);

		$this->Url=htmlspecialchars($s);
		return $this->Url;
	}

	private function Make_Replace(){
		global $zbp;
		$s=$this->PreUrl;

		if(isset($this->Rules['{%page%}'])){
			if($this->Rules['{%page%}']=='1'||$this->Rules['{%page%}']=='0'){
				$this->Rules['{%page%}']='';
			}
		}else{
			$this->Rules['{%page%}']='';
		}
		if($this->Rules['{%page%}']==''){
			if(substr_count($s,'{%page%}')==1&&substr_count($s,'{')==2){
				$s=$zbp->host;
			}
			preg_match('/(?<=\})[^\{\}%\/]+(?=\{%page%\})/i', $s, $matches);
			if(isset($matches[0])){
				$s=str_replace($matches[0],'',$s);
			}
			if(substr($s,-9)=='{%page%}/')$s=substr($s,0,strlen($s)-1);
		}

		$this->Rules['{%host%}']=$zbp->host;
		foreach ($this->Rules as $key => $value) {
			$s=str_replace($key, $value, $s);
		}

		$this->Url=htmlspecialchars($s);
		return $this->Url;
	}

	public function Make(){
		if($this->MakeReplace){
			return $this->Make_Replace();
		}else{
			return $this->Make_Preg();
		}
	}

	static public function Rewrite_url($url,$type){
		global $zbp;
		switch ($zbp->categorylayer) {
			case 4:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?|[^\./]+?/[^\./]+?/[^\./]+?|[^\./]+/[^\./]+?/[^\./]+?/[^\./]+?';
				break;
			case 3:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?|[^\./]+?/[^\./]+?/[^\./]+?';
				break;
			case 2:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?';
				break;
			default:
				$fullcategory='[^\./]+?';
				break;
		}

		$s=$url;
		$s=str_replace('%page%', '%poaogoe%', $s);
		$url=str_replace('{%host%}', '^', $url);
		$url=str_replace('.', '\\.', $url);
		if($type=='index'){
			$url=str_replace('%page%', '%poaogoe%', $url);
			preg_match('/[^\{\}]+(?=\{%poaogoe%\})/i', $s, $matches);
			if(isset($matches[0])){
				$url=str_replace($matches[0],'(?:'.$matches[0].')<:1:>',$url);
			}
			$url = $url . '$';
			$url=str_replace('%poaogoe%', '([0-9]*)', $url);
		}
		if($type=='cate'||$type=='tags'||$type=='date'||$type=='auth'){
			$url=str_replace('%page%', '%poaogoe%', $url);
			preg_match('/(?<=\})[^\{\}]+(?=\{%poaogoe%\})/i', $s, $matches);
			if(isset($matches[0])){
				$url=str_replace($matches[0],'(?:'.$matches[0].')?',$url);
			}
			$url = $url . '$';
			$url=str_replace('%poaogoe%', '([0-9]*)', $url);
			$url=str_replace('%id%', '([0-9]+)', $url);
			$url=str_replace('%date%', '([0-9\-]+)', $url);
			if($type=='cate'){
				$url=str_replace('%alias%', '('.$fullcategory.')', $url);
			}else{
				$url=str_replace('%alias%', '([^/_]+)', $url);
			}
		}
		if($type=='page'||$type=='article'){
			if(strpos($url, '%alias%')===false){
				$url = $url . '$';
				$url=str_replace('%id%', '([0-9]+)', $url);
			}else{
				$url = $url . '$';
				if($type=='article'){
					$url=str_replace('%alias%', '([^/]+)', $url);
				}else{
					$url=str_replace('%alias%', '(.+)', $url);
				}
			}
			$url=str_replace('%category%', '(?:'.$fullcategory.')', $url);
			$url=str_replace('%author%', '[^\./]+', $url);
			$url=str_replace('%year%', '[0-9]<:4:>', $url);
			$url=str_replace('%month%', '[0-9]<:1,2:>', $url);
			$url=str_replace('%day%', '[0-9]<:1,2:>', $url);
		}
		$url=str_replace('{', '', $url);
		$url=str_replace('}', '', $url);
		$url=str_replace('<:', '{', $url);
		$url=str_replace(':>', '}', $url);
		$url=str_replace('/', '\/', $url);
		return '/' . $url . '/';
	}


	public function Make_htaccess(){
		global $zbp;
		$s='<IfModule mod_rewrite.c>' . "\r\n";
		$s .='RewriteEngine On' . "\r\n";
		$s .= "RewriteBase " . $zbp->cookiespath . "\r\n";

		$s .= 'RewriteCond %{REQUEST_FILENAME} !-f' . "\r\n";
		$s .= 'RewriteCond %{REQUEST_FILENAME} !-d' . "\r\n";
		$s .= 'RewriteRule . '.$zbp->cookiespath.'index.php [L]' . "\r\n";
		$s .= '</IfModule>';
		return $s;
	}


	public function Make_webconfig(){
		global $zbp;

		$s  ='<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
		$s .='<configuration>'. "\r\n";
		$s .=' <system.webServer>' . "\r\n";

		$s .='  <rewrite>' . "\r\n";
		$s .='   <rules>' . "\r\n";

		$s .='    <rule name="'.$zbp->cookiespath.'Imported Rule 1" stopProcessing="true">' . "\r\n";
		$s .='     <match url="^" ignoreCase="false" />' . "\r\n";
		$s .='      <conditions logicalGrouping="MatchAny">' . "\r\n";
		$s .='       <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="true" />' . "\r\n";
		$s .='       <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="true" />' . "\r\n";
		$s .='      </conditions>' . "\r\n";
		$s .='      <action type="None" />' . "\r\n";
		$s .='    </rule>' . "\r\n";

		$s .='    <rule name="'.$zbp->cookiespath.'Imported Rule 2" stopProcessing="true">' . "\r\n";
		$s .='     <match url="^" ignoreCase="false" />' . "\r\n";
		$s .='     <action type="Rewrite" url="index.php" />' . "\r\n";
		$s .='    </rule>' . "\r\n";

		$s .='   </rules>' . "\r\n";
		$s .='  </rewrite>' . "\r\n";
		$s .=' </system.webServer>' . "\r\n";
		$s .='</configuration>' . "\r\n";

		return $s;
	}

	public function Make_nginx(){
		global $zbp;
		$s ='';
		$s .='if (-f $request_filename/index.html){' . "\r\n";
		$s .='	rewrite (.*) $1/index.html break;' . "\r\n";
		$s .='}' . "\r\n";
		$s .='if (-f $request_filename/index.php){' . "\r\n";
		$s .='	rewrite (.*) $1/index.php;' . "\r\n";
		$s .='}' . "\r\n";
		$s .='if (!-f $request_filename){' . "\r\n";
		$s .='	rewrite (.*) '.$zbp->cookiespath.'index.php;' . "\r\n";
		$s .='}' . "\r\n";
		return $s;
	}

	public function Make_httpdini(){
		global $zbp;

		$s  ='[ISAPI_Rewrite]' . "\r\n";
		$s .="\r\n";

		$s .= $this->Rewrite_httpdini($zbp->option['ZC_INDEX_REGEX'],'index') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_DATE_REGEX'],'date') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_AUTHOR_REGEX'],'auth') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_TAGS_REGEX'],'tags') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_CATEGORY_REGEX'],'cate') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_ARTICLE_REGEX'],'article') . "\r\n";
		$s .= $this->Rewrite_httpdini($zbp->option['ZC_PAGE_REGEX'],'page') . "\r\n";

		return $s;
	}

	public function Rewrite_httpdini($url,$type){
		global $zbp;
		switch ($zbp->categorylayer) {
			case 4:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?|[^\./]+?/[^\./]+?/[^\./]+?|[^\./]+/[^\./]+?/[^\./]+?/[^\./]+?';
				break;
			case 3:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?|[^\./]+?/[^\./]+?/[^\./]+?';
				break;
			case 2:
				$fullcategory='[^\./]+?|[^\./]+?/[^\./]+?';
				break;
			default:
				$fullcategory='[^\./]+?';
				break;
		}

		$s=$url;
		$s=str_replace('%page%', '%poaogoe%', $s);
		$url=str_replace('{%host%}', '', $url);
		$url=str_replace('.', '\\.', $url);
		if($type=='index'){
			$url=str_replace('%page%', '%poaogoe%', $url);
			preg_match('/[^\{\}]+(?=\{%%poaogoe%%\})/i', $s, $matches);
			if(isset($matches[0])){
				$r=0;
				$url=str_replace($matches[0],'(?:'.$matches[0].')<:1:>',$url);
			}
			$url = $url .' '.$zbp->cookiespath .'index\.php\?page=$1&rewrite=$0';
			$url=str_replace('%poaogoe%', '([0-9]*)', $url);
		}
		if($type=='cate'||$type=='tags'||$type=='date'||$type=='auth'){
			$url=str_replace('%page%', '%poaogoe%', $url);
			preg_match('/(?<=\})[^\{\}]+(?=\{%poaogoe%\})/i', $s, $matches);
			if(isset($matches[0])){
				$url=str_replace($matches[0],'(?:'.$matches[0].')?',$url);
			}
			$url = $url .' '.$zbp->cookiespath . 'index\.php\?'. $type .'=$1&page=$2&rewrite=$0';
			$url=str_replace('%poaogoe%', '([0-9]*)', $url);
			$url=str_replace('%id%', '([0-9]+)', $url);
			$url=str_replace('%date%', '([0-9\-]+)', $url);
			if($type=='cate'){
				$url=str_replace('%alias%', '('.$fullcategory.')', $url);
			}else{
				$url=str_replace('%alias%', '([^/_]+)', $url);
			}
		}
		if($type=='page'||$type=='article'){
			if(strpos($url, '%alias%')===false){
				$url = $url .' '.$zbp->cookiespath .'index\.php\?id=$1&rewrite=$0';
				$url=str_replace('%id%', '([0-9]+)', $url);
			}else{
				$url = $url .' '.$zbp->cookiespath .'index\.php\?alias=$1&rewrite=$0';
				$url=str_replace('%alias%', '([^/]+)', $url);
			}
			//$url=str_replace('%category%', '(?:[^\./]+)', $url);
			$url=str_replace('%category%', '(?:'.$fullcategory.')', $url);
			$url=str_replace('%author%', '(?:[^\./]+)', $url);
			$url=str_replace('%year%', '(?:[0-9]<:4:>)', $url);
			$url=str_replace('%month%', '(?:[0-9]<:1,2:>)', $url);
			$url=str_replace('%day%', '(?:[0-9]<:1,2:>)', $url);
		}
		$url=str_replace('{', '', $url);
		$url=str_replace('}', '', $url);
		$url=str_replace('<:', '{', $url);
		$url=str_replace(':>', '}', $url);
		return 'RewriteRule ' . $zbp->cookiespath . $url . ' [I,L]';

	}

}