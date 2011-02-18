<?php
class ControllerParser {
	protected $Document;
	protected $reservedAttributes = array('title');
	public function __construct($xmlData) {
		$this->Document = new DOMDocument();
		preg_match_all('/<([\w\d\_]+)\:/Uis', $xmlData, $regs, PREG_SET_ORDER);
		$namespaces = array();
		forEach($regs as $reg) $namespaces[$reg[1]]=1;
		$controllerTag = '<Controller xmlns:'.join('="/" xmlns:', array_keys($namespaces)).'="/" \1>';
		
		$xmlData = preg_replace('/<Controller([^>*]*)>/Uis', $controllerTag, $xmlData);
		@$this->Document->loadXML($xmlData, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NOENT);
	}
	public static function fromFile($xmlFile) {
		return new self(file_get_contents($xmlFile));
	}
	protected function indent($ct) {
		return str_pad('', $ct, "\t");
	}
	protected function attributesToArray($node, $reserved=null) {
		if ($reserved === null) $reserved = $this->reservedAttributes;
		$props = array();
		if ($node->nodeType==3) return '';
		forEach ($node->attributes as $attribute) {
			if (in_array($attribute->name, $reserved)) continue;
			$props[] = addslashes($attribute->name).'=>'.$attribute->value;
		}
		if (count($props)==0) return '';
		return 'array('.join(',', $props).')';
	}
	protected function processValue($value) {
		//TODO: Tokenize, don't regex.
		$die = (object)array('prep'=>'','state'=>'');
		if (!strstr($value, '{%')) 
			return (object)array('prep'=>'', 'state'=>'\''.addslashes($value).'\'');
			
		$commands = array();
		$ct = 0;
		$commands[$ct]='';
		$tokens = preg_split('/(\'|\{\%|\%\})/Us', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$php = false;
		$buf = array();
		forEach ($tokens as $token) switch ($token) {
			case '{%': if (!$php) {
				$php = true;
				$buf[]='\'.';
			} else {
				$commands[$ct]=$token;
			}
			break;
			case '%}': if ($php) {
				$php = false;
				$buf[]='$__RESULTS['.$ct.']';
				$ct++;
				$commands[$ct]='';
				$buf[]='.\'';
			} else {
				$buf[]=$token;
			}
			break;
			case '\'': if (!$php) {
				$buf[]='\\'.$token;
			} else {
				$commands[$ct]=$token;
			}
			break;
			default: if (!$php) {
				$buf[]=$token;
			} else {
				$commands[$ct].=$token;
			}
		}
		if ($php) throw new Exception('Unclosed \'{%\' in controller XML');
		$value = '\''.join('', $buf).'\'';
		
		$value = preg_replace('/(^|\.)\'\'(\.|$)/Uis', '.', $value);
		$value = preg_replace('/(^\.)|(\.$)/Uis', '', $value);
		
		$preparation = '$ex = function ($dictionary) { extract($dictionary); $__RESULTS=array(); $__RESULTS[]='.join('; $__RESULTS[]=', $commands).'; return $__RESULTS; }; $__RESULTS = $ex();';
			
		
		return (object)array(
			'prep'=>$preparation,
			'state'=>$value
		);
	}
	protected function processNodes($nodes, $indent = 2) {	
		$out = array();
		$in = $this->indent($indent);
		$t = "\t";
		$localNodeNames = array('call','do','jump','return');
		forEach($nodes as $node) {
			if ($node->nodeType===3) continue;
			$jumpRet = '';
			if (!empty($node->prefix) || !in_array(strToLower($node->nodeName), $localNodeNames) ) {
				$args = array('\''.$node->nodeName.'\'');
				$props = $this->attributesToArray($node);
				if (!empty($props)) $args[]=$props;
				$cmd = 'Controller::runComponent($dictionary, '.join(', ', $args).')';
				
				if ($node->childNodes->length > 0) {
					$out=array_merge($out, array(
						$in.'try {',
						$in.$t.'Controller::runComponent('.join(', ', $args).')',
						$in.'} catch (Exception $e) {',
						$in.$t.'$dictionary[\'exception\'] = $e;',
						$this->processNodes($node->childNodes, $indent+1),
						$in.$t.'unset($dictionary[\'exception\']);',
						$in.'}'
					));
				} else {
					$out[]=$in.$cmd;
				}
			} else switch (strToLower($node->nodeName)) {
				case 'jump':
					$jumpRet = "\n".$in.'return $dictionary;';
				case 'call': 
					$args = array($this->processValue($node->getAttribute('action')));
					$props = $this->attributesToArray($node,  array('title', 'action'));
					forEach($args as $i=>$arg) {
						$out[]=$arg->prep;
						$args[$i]=$arg->state;
					}
					if (!empty($props)) $args[]=$props;
					$out[]=$in.'Controller::call($dictionary, '.join(', ', $args).');'.$jumpRet;
					break;
				case 'do':
					if ($node->hasAttribute('if')) {
						$condition = $this->processValue($node->getAttribute('if'));
						$out = array_merge($out, array(
							$in.$condition->prep,
							$in.'if ('.$condition->state.') {',
							$this->processNodes($node->childNodes, $indent+1),
							$in.'}'
						));
					} else if ($node->hasAttribute('while')) {
						$condition = $this->processValue($node->getAttribute('while'));
						$out = array_merge($out, array(
							$in.$condition->prep,
							$in.'while ('.$condition->state.') {',
							$this->processNodes($node->childNodes, $indent+1),
							$in.'}'
						));
					}
					break;
				case 'return':
					$props = $this->attributesToArray($node);
					if (!empty($props))
						$out[]=$in.'$dictionary = array_merge($dictionary, '.$props.');';
					$out[]=$in.'return $dictionary;';
			}
		}
		return join("\n", $out);
	}
	public function parse() {
		$components = array();
		forEach($this->Document->getElementsByTagName('*') as $elem) {
			if (!empty($elem->prefix)) {
				$components[]=(object)array(
					'module'=>$elem->prefix,
					'component'=>$elem->localName
				);
			}
		}
		$methods = array();
		
		forEach($this->Document->getElementsByTagName('Action') as $elem) {
			$methods[] = join("\n", array(
				"\t'".$elem->getAttribute('id').'\' => function ($dictionary, $__CONTROLLER) {',
				"\t\t".'$__ACTION = \''.$elem->getAttribute('id').'\';',
				$this->processNodes($elem->childNodes),
				"\t}"
			));
		}
		return join("\n", array(
			'$controller = array(',
			join("\n", $methods),
			');'
		));
	}
}

