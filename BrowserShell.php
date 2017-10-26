<?php
require_once 'lib/Browser.php';

/**
 * Interpréteur de commandes pour le navigateur scriptable PHP.
 * Voir le détail de l'utilisateur dans browser-shell.man
 *  
 * @author Francois RAOULT
 * 
 * @license GNU/LGPL
 * 
 * @see Browser
 */


class BrowserShell
{
	/**
	 * @var Browser
	 */
	protected $browser = NULL;

	/**
	 * @var Form
	 */
	protected $form    = NULL;
	
	/**
	 * @var array
	 */
	protected $vars    = array();
	
	/**
	 * @var array
	 */
	protected $labels  = array();

	/**
	 * @var array
	 */
	protected $config  = array();
	
	/**
	 * @var array
	 */
	protected $cmd     = array();
	
		
	// ------------------------------------------------------------------------
	//
	// CREATION / DESTRUCTION / EXECUTION
	//
	// ------------------------------------------------------------------------
	
	/**
	 * Exécution d'un fichier de commande
	 */
	public static function run($filename, $fileconfig=null, $debuglevel=null, $pause=null, array $configvar=[])
	{
		self::create()
			->setConfigFile($fileconfig)
			->setDebugLevel($debuglevel)
			->setConfigVar($configvar)
			->setPauseDuration($pause)
			->execute($filename);
	}
	
	/**
	 * Création en mode chainée
	 * @return BrowserShell
	 */
	public static function create($fileconfig=null)
	{
		return new self($fileconfig);
	}
	
	/**
	 * Constructeur
	 */
	public function __construct($fileconfig=null)
	{
		$this->browser = new Browser();
		
		if (isset($fileconfig))
		{
			$this->setConfigFile($fileconfig);
		}
	}
	
	/**
	 * Destructeur
	 */
	public function __destruct()
	{
	}
	
	/**
	 * Niveau de debug
	 * @param mixed $debuglevel TRUE pour les messages de debug. FALSE sinon. Si NULL, ne change pas la valeur
	 * @return $this
	 */
	public function setDebugLevel($debuglevel)
	{
		if ($debuglevel === NULL)
		{
			return $this;
		}
		
		$this->config['shell']['debug'] = (int)$debuglevel;
		
		return $this;
	}

	/**
	 * Variables de script
	 * @param array $configvar
	 * @return $this
	 */
	public function setConfigVar(array $configvar)
	{
		foreach($configvar as $name => $value)
		{
			$this->config['data'][$name] = $value;
		}
		
		return $this;
	}
	
	/**
	 * Durée de la pause après chaque action de navigation
	 * @param integer $pause
	 */
	public function setPauseDuration($pause)
	{
		if ($pause === NULL)
		{
			return $this;
		}
		
		if (! ctype_digit($pause))
		{
			throw new Exception('Pause duration is not an integer');
		}
		
		$this->config['shell']['pause-duration'] = (int)$pause;
		
		return $this;
	}
	
	/**
	 * Application de la configuration
	 * 
	 * @param string $fileconfig Fichier INI de configuration
	 * @throws Exception
	 * @return $this
	 */
	public function setConfigFile($fileconfig)
	{
		if ($fileconfig === NULL)
		{
			return $this;
		}
		
		$this->browser->setCookieFile('cookies.txt');
		
		if (empty($fileconfig))
		{
			$this->config = array();
			return $this;
		}
		
		if (!file_exists($fileconfig))
		{
			throw new Exception('Config file "'.$fileconfig.'" not found');
		}
		
		$this->config = parse_ini_file($fileconfig, TRUE);

		foreach($this->config as $section => &$cfg)
		{
			foreach($cfg as $name => &$value)
			{
				switch (strtolower($value))
				{
					case 'true':
					case 'on':
					case 'yes':
						$value = TRUE;
						break;
						
					case 'false':
					case 'off':
					case 'no':
					case 'none':
						$value = TRUE;
						break;
						
					case 'null':
						$value = NULL;
						break;
				}
			}
			unset($value);
		}
		unset($cfg);
		
		if (isset($this->config['browser']))
		{
			$cfg = $this->config['browser'];
			
			if (isset($cfg['cookie-file']))
			{
				$this->browser->setCookieFile($cfg['cookie-file']);
			}
			if (isset($cfg['exit-if-error']))
			{
				$this->browser->setExitIfHttpError($cfg['exit-if-error']);
			}
			if (isset($cfg['user-agent']))
			{
				$this->browser->setUserAgent($cfg['user-agent']);
			}
		}

		if (isset($this->config['shell']))
		{
			$cfg = $this->config['shell'];
			
			if (isset($cfg['debug']))
			{
				$this->setDebugLevel($cfg['debug']);
			}
		}
		
		return $this;
	}
	
	/**
	 * Exécuter les commandes indiquées dans le fichier
	 * @param string $filename
	 */
	public function execute($filename)
	{
		if (substr($filename,0,6) != 'php://' && ! file_exists($filename))
		{
			throw new Exception('Commands file "'.$filename.'" not found !');
		}

		$handle = fopen($filename, 'r');
		
		$this->debug("Start file $filename");
		$label = NULL;
		while(! feof($handle) && $line = fgets($handle))
		{
			$line = trim($line);
			if (empty($line))
			{
				continue;
			}
			if ($line{0} == ';')
			{
				continue;
			}
				
			if (preg_match('`^\[(.+?)\]`', $line, $m))
			{
				$this->debug("set label $m[1]");
				$label = $m[1];
				$this->_label($label);
				continue;
			}
				
			$command = $line;
			$param   = null;
				
			$space_pos = strpos($line, ' ');
				
			if ($space_pos !== FALSE)
			{
				$command  = substr($line, 0, $space_pos);
				$param    = substr($line, $space_pos+1);
				$commande = strtolower(trim($command));
				$param    = trim($param);
			}
				
			$this->cmd[$label][] = array('command' => $command, 'params'=>$param);
			$this->_caller($command, $param);
		}
		
		$this->debug("End of $filename");
		
		fclose($handle);
		
	}

	// ------------------------------------------------------------------------
	//
	// COMMANDES
	//
	// ------------------------------------------------------------------------
	
	
	/**
	 * Navigation
	 * @param string $url
	 */
	protected function cmd_browse($url)
	{
		$url = $this->browser->buildUrl($url);
		$this->browser->browse($url);
		$this->_afterNavigate();
	}
	
	/**
	 * Soumission de formulaire
	 * @param unknown $element
	 */
	protected function cmd_submit($element=null)
	{
		$this->form->submit($element);
		$this->_afterNavigate();
	}
	
	/**
	 * Enregistrement dans un fichier
	 * @param string $filename
	 */
	protected function cmd_write($filename)
	{
		file_put_contents($filename, $this->browser->getRawData());
	}
	
	/**
	 * Naviguer vers un label / historique
	 * @param unknown $destination
	 */
	protected function cmd_history($destination)
	{
		$resend = TRUE;
		if (strtolower(substr(trim($destination), -6)) == 'browse')
		{
			$resend = FALSE;
		}
		
		if (strtolower(substr($destination, 0, 5)) == 'goto:')
		{
			$goto = trim(substr($destination, 5));
			if (preg_match('`^\[(.+?)\]`', $goto, $m))
			{
				$goto   = $this->labels[$m[1]];
			}
			$this->browser->historyGoTo($goto, $resend);
		}
		else
		{
			$this->browser->historyGo($destination, $resend);
		}

		$this->_afterNavigate();
	}
	
	
	/**
	 * Sélectionner un formulaire à utiliser par la suite
	 * @param unknown $target
	 */
	protected function cmd_useform($target=null)
	{
		$this->form = $this->browser->getForm($target);
	}
	
	/**
	 * Réinitialiser le formulaire en cours
	 */
	protected function cmd_reset()
	{
		$this->form->reset();
	}
	
	/**
	 * Donner une valeur à un champ de formulaire
	 * @param string $fieldname
	 * @param string $value
	 */
	protected function cmd_set($fieldname, $value)
	{
		$this->_checkFormElementType($fieldname, '!checkbox');
		
		$v = trim($value);
		if (strlen($v) > 2 && $v{0} == '[')
		{
			$t = json_decode($v, TRUE);
			if ($t)
			{
				$value = $t;
			}
		}
		$this->form->setData($fieldname, $value);
	}
	
	/**
	 * Cocher une checkbox du formulaire
	 * @param string $targetelement
	 */
	protected function cmd_check($targetelement)
	{
		$this->_checkFormElementType($targetelement, 'checkbox');
		$this->form->checkField($targetelement);
	}

	/**
	 * Décocher une checkbox du formulaire
	 * @param string $targetelement
	 */
	protected function cmd_uncheck($targetelement)
	{
		$this->_checkFormElementType($targetelement, 'checkbox');
		$this->form->uncheckField($targetelement);
	}
	
	/**
	 * Supprimer un champ de la liste des données transmises
	 * @param string $fieldname
	 */
	protected function cmd_unset($fieldname)
	{
		$this->form->removeData($fieldname);
	}
	
	/**
	 * Définir une variable de script
	 * @param string $varname
	 * @param string $value
	 */
	protected function cmd_setvar($varname, $value)
	{
		$this->vars[$varname] = $value;
	}
	
	/**
	 * Quitter
	 */
	protected function cmd_exit()
	{
		exit(0);
	}
	
	/**
	 * Rejouer un bloc de commande
	 * @param string $blockname
	 */
	protected function cmd_play($blockname)
	{
		foreach($this->cmd[$blockname] as $cmdline)
		{
			$command = $cmdline['command'];
			$params  = $cmdline['params'];
			$this->_caller($command, $params);
		}
	}
	
	
	/**
	 * Inclure et lancer un autre fichier de commandes
	 * (isolé du fichier principal)
	 * 
	 * @param string $filename
	 */
	protected function cmd_include($filename)
	{
		$shell = clone ($this);      // Même configuration, même navigateur, etc.
		$shell->labels = array();    // Remise des label à 0 pour le fichier include
		$shell->execute($filename);  // Lance le fichier inclus
	}
	
	/**
	 * Afficher le contenu de la page
	 */
	protected function cmd_print()
	{
		echo $this->browser->getRawData();
	}
	
	/**
	 * Effectuer une pause
	 * @param integer $duration
	 */
	protected function cmd_pause($duration)
	{
		sleep((int)$duration);
	}

	/**
	 * Afficher un message sur la console
	 * @param string $message
	 */
	protected function cmd_message($message)
	{
		echo $message . PHP_EOL;
	}
	
	/**
	 * Affiche un message si mode debug
	 * @param string $message
	 */
	protected function cmd_debug($message)
	{
		$this->debug($message);
	}
	
		
	// ------------------------------------------------------------------------
	//
	// LECTURE DES VARIABLES %...%
	//
	// ------------------------------------------------------------------------
	
	/**
	 * Récupérer une variable de script
	 * @param string $varname
	 * @return mixed
	 */
	protected function get_var($varname)
	{
		if (! key_exists($varname, $this->vars))
		{
			throw new Exception('Script variable "'.$varname.'" doesn\'t exists !');
		}
		$var =  $this->vars[$varname];          // Variable de script
		$var = $this->_parseParam($var, null);  // Retourne un tableau [var]. les variables %% ont été interprétées
		$var = reset($var);                     // Récupère l'unique élément
		return $var;
	}
	
	/**
	 * Récupérer une variable de configuration
	 * @param string $varname
	 * @return mixed
	 */
	protected function get_cfg($varname)
	{
		if (empty($this->config['data']) || ! key_exists($varname, $this->config['data']))
		{
			throw new Exception('Configuration variable "'.$varname.'" doesn\'t exists !');
		}
		$var = $this->config['data'][$varname]; // Variable de configuration
		$var = $this->_parseParam($var, null);  // Retourne un tableau [var]. les variables %% ont été interprétées
		$var = reset($var);                     // Récupère l'unique élément
		return $var;
	}
	
	/**
	 * Récupérer la date au format indiqué
	 * @param string $format
	 * @return string
	 */
	protected function get_date($format)
	{
		$format = $this->_parseDateFormat($format);
		return date($format);
	}
	
	/**
	 * Récupérer la date à un interval donné, au format indiqué
	 * @param string $interval
	 * @param string $format
	 * @return string
	 */
	protected function get_date_interval($interval, $format)
	{
		$date = new DateTime($interval);
		$format = $this->_parseDateFormat($format);
		return $date->format($format);
	}
	
	/**
	 * Récupère une variable système
	 * @param string $param
	 * @throws Exception
	 * @return string 
	 */
	protected function get_system($param)
	{
		$subparam = NULL;
		$pos      = strpos($param, ':');
		if ($pos !== FALSE)
		{
			$param = substr($param, 0, $pos);
			$subparam = substr($param, $pos+1);
		}
		
		$param = strtolower(trim($param));
		
		switch ($param)
		{
			case 'timestamp':
				return time();
				
			case 'os':
				$os = strtolower(PHP_OS);
				if (substr($os, 0, 3) == 'win')
				{
					return 'win';
				}
				elseif (substr($os, 0, 5) == 'linux')
				{
					return 'linux';
				}
				return $os;
				
			case 'rand':
				if ($subparam)
				{
					$min_max = explode(':', $subparam);
					$min     = count($min_max) > 0 ? $min_max[0] : null;
					$max     = count($min_max) > 1 ? $min_max[1] : null;
					return rand($min, $max);
				}
				return rand();
				
			case 'login':
				return strtolower(get_current_user());
				
			default:
				throw new Exception('Unknown parameter '.$param.' to get "system" variable !');
		}
		
	}
	
	// ------------------------------------------------------------------------
	//
	// FONCTIONS D'APPEL
	//
	// ------------------------------------------------------------------------
	
	/**
	 * Exécution d'une ligne de commande
	 * @param string $command
	 * @param string $param_str
	 * @throws Exception
	 */
	protected function _caller($command, $param_str)
	{
		$command = strtolower(trim($command));
		$param_str   = trim($param_str);
		
		$params = $this->_parseParam($param_str);
		
		$opt_param = count($params) > 0 ? $params[0] : null;
		
		if (empty($params))
		{
			$this->debug("execute $command()");
		}
		else
		{
			$this->debug("execute $command(\"%s\")", implode('", "', $params));
		}
		
		switch ($command)
		{
			case 'browse':
				$this->cmd_browse($params[0]);
				break;
				
			case 'submit':
				$this->cmd_submit($opt_param);
				break;
				
			case 'write':
				$this->cmd_write($params[0]);
				break;
				
			case 'history':
				$this->cmd_history($params[0]);
				break;
				
			case 'use-form':
				$this->cmd_useform($opt_param);
				break;
				
			case 'reset':
				$this->cmd_reset();
				break;
				
			case 'set':
				$this->cmd_set($params[0], $params[1]);
				break;

			case 'check':
				$this->cmd_check($params[0]);
				break;

			case 'uncheck':
				$this->cmd_uncheck($params[0]);
				break;
				
			case 'unset':
				$this->cmd_unset($params[0]);
				break;
				
			case 'set-var':
				$this->cmd_setvar($params[0], $params[1]);
				break;
				
			case 'exit':
				$this->cmd_exit();
				break;

			case 'play':
				$this->cmd_play($params[0]);
				break;

			case 'include':
				$this->cmd_include($params[0]);
				break;
				
			case 'pause':
				$this->cmd_pause($opt_param ? $opt_param : 1);
				break;

			case 'print':
				$this->cmd_print();
				break;
				
			case 'message':
				$this->cmd_message($opt_param);
				break;

			case 'debug':
				$this->cmd_debug($opt_param);
				break;
				
			default:
				throw new Exception('Unknonwn command "'.$command.'" !');
		}
		
	}
	
	/**
	 * Récupération d'une variable %%
	 * @param string  $source
	 * @param string  $params
	 * @param string  $default
	 * @return string
	 */
	protected function _getter($source, $params, $default=null)
	{
		$source = strtolower(trim($source));
		$params = trim($params);
		
		switch ($source)
		{
			case 'var':
				return $this->get_var($params);
				break;
				
			case 'cfg':
				return $this->get_cfg($params);
				break;
				
			case 'date':
				return $this->get_date($params);
				break;
				
			case 'date_interval':
				$param_list = str_getcsv($params, ':', '"', '\\');
				return $this->get_date_interval($param_list[0], $param_list[1]);
				break;
				
			default:
				return $default;
		}
		
	}
	
	/**
	 * Définition d'un label
	 * @param string $label
	 */
	protected function _label($label)
	{
		$this->labels[$label] = $this->browser->getHistoryPointer();
	}
	
	
	// ------------------------------------------------------------------------
	//
	// OUTILS
	//
	// ------------------------------------------------------------------------
	
	
	/**
	 * Après un browse() ou un submit() ou un history()
	 */
	protected function _afterNavigate()
	{
		$this->cmd_useform();
		if (isset($this->config['shell']['pause-duration']))
		{
			$pause = (int)$this->config['shell']['pause-duration'];
			sleep($pause);
		}
	}

	/**
	 * Vérifie l'existance d'un élément de formulaire
	 * si nécessaire et génère une exception si n'existe pas
	 * @param  string $fieldname
	 * @throws Exception
	 */
	protected function _checkFormElement($fieldname)
	{
		if (isset($this->config['shell']['check-formelement-exists']))
		{
			if (! $this->form->hasElement($fieldname))
			{
				throw new Exception('Form element '.$fieldname.' not found');
			}
		}
	}

	/**
	 * Vérifie le type d'un élément. Génère une exception
	 * @param string $fieldname
	 * @param string $expected
	 * @throws Exception
	 */
	protected function _checkFormElementType($fieldname, $expected)
	{
		$this->_checkFormElement($fieldname);
		
		if (empty($this->config['shell']['check-formelement-type']) || empty($expected))
		{
			return;
		}
		
		$type = $this->form->getElementType($fieldname);
		
		$type       = strtolower($type);
		
		if ($expected{0} == '!')
		{
			$unexpected = strtolower(substr($expected, 1));
			if ($type == $unexpected)
			{
				throw new Exception('Form element '.$fieldname.' must be not a "'.$expected.'" element');
			}
			return;
		}
		
		$expected   = strtolower($expected);
		
		if ($type != $expected)
		{
			throw new Exception('Form element '.$fieldname.' is not a "'.$expected.'" element');
		}
		
		return;
	}
	
	/**
	 * Analyse la chaine de paramètres de la commandes :
	 * - Sépare les différents paramètres, si $separator n'est pas vide
	 * - Analyse chaque élément pour remplacer variable %type:data% par leur valeur
	 * @param string $param
	 * @param string $separator
	 * @return string[]
	 */
	protected function _parseParam($param, $separator='=')
	{
		if (empty($param))
		{
			return [];
		}
		
		$param_list = [$param];
		
		if (! empty($separator))
		{
			$separator_pos = strpos($param, $separator);
			
			if ($separator_pos === FALSE)
			{
				$param_list = [$param];
			}
			else
			{
				$param_list = str_getcsv($param, $separator, '"', '\\');
			}
		}
		
		
		foreach($param_list as &$str)
		{
			$str = trim($str);
			$str = str_replace('\\\\', chr(6), $str); // \ échapés
			$str = str_replace('\\%',  chr(7), $str); // % échapés
			
			// Ca c'est de la regex !
			//
			// Récupérer les variables qui sont sous la forme %source:parametres%
			// Et les variables imbriquées {%source:parametres%}
			//
			// Par exemple, Si :
			// cfg:interval = "-2 months"
			// cfg:period   = "J"
			// var:mavariable = "interval"
			// Alors :
			// %date_interval:-{%cfg:{%var:mavariable%}%}:{%cfg:period%}%
			// Va être interprété en 3 étapes : 
			// 1- var:mavariable (retourne "interval") et cfg:period (retourne "J")
			//  -> %date_interval:-{%cfg:interval%}:J%
			// 2- cfg:interval (retourne "2 months")
			//  -> %date_interval:-2 months:J%
			// 3- date_interval:-2 months:J (retourne la valeur de date_interval(-2 months, now)
			//
			while (preg_match_all('`{%([a-z_]+?):((?:(?!{%).)+?)%}|%([a-z_]+?):((?:(?!{%).)+?)%`', $str, $matches, PREG_SET_ORDER))
			{
				foreach($matches as $m)
				{
					$m      = array_values(array_filter($m));   // Nettoyage $m[1]/$m[2] ou $m[3]/$m[4] selon la regex utilisée
					$match  = $m[0];                            // Chaine %source:param% trouvée
					$source = $m[1];                            // Source (var, cfg, system, date, date_interval, etc.)
					$mparam = $m[2];                            // Paramètre
					
					$value  = $this->_getter($source, $mparam, $m[0]); // Valeur finale : Appel du getter. Valeur par défaut = aucun remplacement
					
					$str = str_replace($match, $value, $str);   // Remplacement de la chaine par sa valeur
				}
			}
			
			$str = str_replace(chr(7), '%', $str);  // % échapés remis en place
			$str = str_replace(chr(6), '\\', $str); // \ échapés remis en place
		}
		
		return $param_list;
	}
		
	/**
	 * Analyse un format de date pour sortir un format PHP
	 * @param string $dateformat
	 * @return string
	 */
	protected function _parseDateFormat($dateformat)
	{
		$phpformat = $dateformat;
		
		// Déja au format php
		if (strtolower(substr($dateformat, 0, 4)) == 'php:')
		{
			return substr($dateformat, 4);
		}
		
		// Echappement des caractères non utilisés dans le script
		
		$escape = 'djlNSwzWFmntLoYyaBgGhHisueIOPTZcrU';
		for($i=0; $i<strlen($escape); $i++)
		{
			$char = $escape{$i};
			$phpformat = str_replace($char, '\\'.$char, $phpformat);
		}
		
		// Remplacement
		
		$phpformat = str_replace('AAAA', 'Y', $phpformat);
		$phpformat = str_replace('AA',   'y', $phpformat);
		$phpformat = str_replace('MMM',  'M', $phpformat);
		$phpformat = str_replace('MM',   'm', $phpformat);
		$phpformat = str_replace('M',    'n', $phpformat);
		$phpformat = str_replace('JJJ',  'D', $phpformat);
		$phpformat = str_replace('JJ',   'd', $phpformat);
		$phpformat = str_replace('j',    'w', $phpformat);
		$phpformat = str_replace('J',    'j', $phpformat);
		$phpformat = str_replace('SS',   'W', $phpformat);
		
		// Echappement des caractères restants
		
		$escape = 'AMD';
		for($i=0; $i<strlen($escape); $i++)
		{
			$char = $escape{$i};
			$phpformat = str_replace($char, '\\'.$char, $phpformat);
		}
		
		return $phpformat;
	}
	
	/**
	 * Message de debug
	 * @param unknown $message
	 */
	protected function debug($message)
	{
		if (isset($this->config['shell']['debug']))
		{
			$args = func_get_args();
			array_shift($args);
			echo vsprintf($message, $args). PHP_EOL;
		}
	}
	
} // class end



$included = get_included_files();
if (empty($included) || reset($included) == __FILE__)
{
	//
	// Exécution en ligne de commande
	//
	
	$options = getopt('', ['file::', 'stdin::', 'config::', 'debug::', 'pause::']);
	
	$file   = isset($options['file'])   ? $options['file']   : NULL;
	$config = isset($options['config']) ? $options['config'] : NULL;
	$debug  = isset($options['debug'])  ? $options['debug']  : NULL;
	$pause  = isset($options['pause'])  ? $options['pause']  : NULL;
	
	if (is_array($file))   $file = array_pop(array_filter($file));
	if (is_array($config)) $file = array_pop(array_filter($config));
	if (is_array($debug))  $file = array_pop(array_filter($debug));
	if (is_array($pause))  $file = array_pop(array_filter($pause));

	$data    = array();
	foreach($argv as $arg)
	{
		$m = array();
		if (empty($name) && preg_match('`--var:([A-Za-z0-9\s_-]+)=(.*)`', $arg, $m))
		{
			$data[$m[1]] = $m[2];
			continue;
		}
	}
	
	if (empty($file) && array_key_exists('stdin', $options))
	{
		$file = 'php://stdin';
	}
	
	if (empty($file))
	{
		fwrite(STDERR, 'Option --file=command_file or option --stdin required' . PHP_EOL);
		exit(-1);
	}
	
	BrowserShell::run($file, $config, $debug, $pause, $data);
}

