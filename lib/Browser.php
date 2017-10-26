<?php
/**
 * Pseudo-navigateur en mode Script
 * Nécessite la classe de gestion de formulaire Form
 * et de requete HTTPResquest
 * 
 * @author Francois RAOULT
 * 
 * @license GNU/LGPL
 * 
 * @see HTTPRequest
 * @see Form
 */

require_once 'HTTPRequest.php';
require_once 'Form.php';

class Browser
{

	/**
	 * Historique
	 * @var HTTPRequest[]
	 */
	protected $history = array();
	
	/**
	 * Pointeur de l'historique
	 * @var integer
	 */
	protected $historyPointer = -1;

	/**
	 * User Agent
	 * @var string
	 */
	protected $userAgent = NULL;
	
	/**
	 * Fichier des cookies
	 * @var string
	 */
	protected $cookieFile = 'cookie.txt';
	
	/**
	 * Arrêter le script si erreur HTTP
	 * @var boolean
	 */
	protected $exitIfError = FALSE;
	
	/**
	 * URL actuelle (modifiée si redirection)
	 * @var string
	 */
	protected $url = NULL;

	/**
	 * Données brutes reçues
	 * @var string
	 */
	protected $rawData = '';
	
	/**
	 * DOM
	 * @var DOMDocument
	 */
	protected $dom = NULL;
	
	/**
	 * Code HTTP reçu
	 * @var integer
	 */
	protected $http_code = NULL;
	
	/**
	 * Type MIME reçu
	 * @var string
	 */
	protected $mime = NULL;
	
	
	/**
	 * Données à envoyer à la prochaine requête
	 * @var array
	 */
	protected $formData = array();
	
	
	/**
	 * Création en mode inline
	 * 
	 * @param string $userAgent
	 * @return $this
	 */
	public static function create($userAgent=NULL)
	{
		return new self($userAgent);
	}
	
	
	/**
	 * Constructeur
	 * @param string $userAgent
	 */
	public function __construct($userAgent=NULL)
	{
		
		if ($userAgent)
		{
			$this->setUserAgent($userAgent);
		}
			
	}
	
	/**
	 * Destructeur
	 */
	public function __destruct()
	{
		
	}

	
	/**
	 * Fichier contenant les cookies
	 * @param string $file
	 * @return $this
	 */
	public function setCookieFile($file)
	{
		$this->cookieFile = $file;
		return $this;
	}
	
	/**
	 * Définir un User-Agent
	 * @param string $userAgent
	 * @return $this
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = $userAgent;
		return $this;
	}
	
	/**
	 * Arrêter PHP si erreur HTTP ?
	 * @param boolean $exitIfError
	 * @return $this
	 */
	public function setExitIfHttpError($exitIfError)
	{
		$this->exitIfError = (boolean)$exitIfError;
		return $this;
	}
	
	/**
	 * Naviger (méthode GET) vers une URL
	 * @param string $url
	 * @return string l'URL actuelle (en cas de redirection)
	 */
	public function browse($url)
	{		
		$request = HTTPRequest::create($url);

		$this->url = $url;
		$this->_go($request);
		
		return $this->url;
	}
	
	/**
	 * Récupère l'URL actuelle
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}
	
	
	/**
	 * Cherche si l'URL actuelle contient la partie indiquée
	 * @param string $part
	 * @return boolean
	 */
	public function urlContain($part)
	{
		return strpos($this->url, $part) !== FALSE;
	}
	
	/**
	 * Vérifie que le composant d'URL demandé soit le même que celui donné (insensible à la casse)
	 * @param string $part
	 * @param integer $component
	 * @return boolean
	 */
	public function urlComponentIs($part, $component)
	{
		return (strtolower(parse_url($this->url, $component)) == strtolower($part));
	}
	
	/**
	 * Vérifie que le protocole soit identique à celui demandé
	 * @param string $protocol
	 * @return boolean
	 */
	public function isProtocol($protocol)
	{
		return $this->urlComponentIs($protocol, PHP_URL_SCHEME);
	}

	/**
	 * Vérifie que l'hôte soit identique à celui demandé
	 * @param string $host
	 * @return boolean
	 */
	public function isHost($host)
	{
		return $this->urlComponentIs($scheme, PHP_URL_HOST);
	}
	
	/**
	 * Vérifie que le port soit le identique à celui demandé
	 * @param integer $port
	 * @return boolean
	 */
	public function isPort($port)
	{
		return $this->urlComponentIs($scheme, PHP_URL_PORT);
	}

	/**
	 * Vérifie que le port soit le identique à celui demandé
	 * @param string $path
	 * @return boolean
	 */
	public function isPath($path)
	{
		return $this->urlComponentIs($scheme, PHP_URL_PATH);
	}
	
	/**
	 * Vérifie que le "fichier" (dernière partie du path) soit le identique à celui demandé
	 * @param string $file
	 * @return boolean
	 */
	public function isFile($file)
	{
		return strtolower($file) == basename(strtolower(parse_url($this->url, $component)));
	}

	/**
	 * Vérifie que le "dossier" (le path sauf la dernière partie) soit identique à celui demandé
	 * @param string $dir
	 * @return boolean
	 */
	public function isDir($dir)
	{
		return strtolower($dir) == dir(strtolower(parse_url($this->url, $component)));
	}
	
	/**
	 * Vérifie que l'anchre (la partie après le #) soit le identique à celui demandé
	 * @param string $anchor
	 * @return boolean
	 */
	public function isAnchor($anchor)
	{
		return $this->urlComponentIs($anchor, PHP_URL_FRAGMENT);
	}
	

	/**
	 * Vérifie si un formulaire (de l'ID ou du nom indiqué) existe sur la page
	 * @param string $nameOrId
	 *                   Si ID, doit commencer par #
	 * @return boolean
	 */
	public function hasForm($nameOrId = NULL)
	{
		if (empty($this->dom))
		{
			return FALSE;
		}
		
		if (! $nameOrId)
		{
			return $this->dom->getElementsByTagName('form')->length > 0;
		}
		
		if (strlen($nameOrId) > 1 && $nameOrId{0} == '#')
		{
			return $this->dom->getElementById(substr($nameOrId, 1)) != NULL;
		}
		
		$domxpath = new DOMXPath($this->dom);
		
		return $domxpath->query('//form[@name="'.$nameOrId.'"]')->length > 0;
	}
	
	/**
	 * Vérifie si un élément de formulaire (de l'ID ou du nom indiqué) existe sur la page
	 * @param string $nameOrId
	 *                   Si ID, doit commencer par #
	 * @return boolean
	 */
	public function hasFormElement($nameOrId)
	{
		if (empty($this->dom))
		{
			return FALSE;
		}
		
		if (! $nameOrId)
		{
			return FALSE;
		}
		
		if (strlen($nameOrId) > 1 && $nameOrId{0} == '#')
		{
			return $this->dom->getElementById(substr($nameOrId, 1)) != NULL;
		}
		
		$domxpath = new DOMXPath($this->dom);
		
		return $domxpath->query('//form/descendant::*[@name="'.$nameOrId.'"]')->length > 0;
	}
	
	/**
	 * Vérifie si l'élément pointé par le chemin XPath existe
	 * @param string $xpath
	 * @return boolean
	 */
	public function elementExists($xpath)
	{
		if (empty($xpath))
		{
			return FALSE;
		}
		
		$domxpath = new DOMXPath($this->dom);
		
		return $domxpath->query($xpath)->length > 0;
	}
	
	
	/**
	 * Construit une URL absolue depuis l'URL relative donnée. Si $base_url est NULL, utilisera l'URL actuelle comme base
	 * @param string $relative_url
	 * @param string $base_url
	 * @throws Exception
	 * @return string
	 */
	public function buildUrl($relative_url, $base_url=NULL)
	{
		if (empty($base_url))
		{
			$base_url = $this->url;
		}
		
		if (empty($base_url))
		{
			return $relative_url;
		}
		
		if (is_string($relative_url))
		{
			$relative_url = parse_url($relative_url);
		}
		
		if (is_string($base_url))
		{
			$base_url = parse_url($base_url);
		}
		

		if (isset($relative_url['path']) && substr($relative_url['path'], 0, 1) != '/')
		{
			$path = isset($base_url['path']) ? $base_url['path']   : '/';
			$path = dirname($path) . '/' . $relative_url['path'];
			$path = str_replace('//', '/', $path);
			$path = str_replace('/./', '/', $path);
			;			while(strpos($path, '/../') !== FALSE)
			{
				$path = preg_replace('`/.+?/../`', '/', $path);
			}
			$relative_url['path'] = $path;
		}
		
		$action = array_merge($base_url, $relative_url);
		
		$scheme = isset($action['scheme']) ? $action['scheme'] : '';
		$host   = isset($action['host'])   ? $action['host']   : '';
		$port   = isset($action['port'])   ? $action['port']   : '';
		$user   = isset($action['user'])   ? $action['user']   : '';
		$pass   = isset($action['pass'])   ? $action['pass']   : '';
		$path   = isset($action['path'])   ? $action['path']   : '/';
		$query  = isset($action['query'])  ? $action['query']  : '';
		
		$userpass  = $user && $pass ? $user.':'.$pass.'@' : ( $user ? $user.'@' : '');
		$hostport  = $port ? $host.':'.$port : $host;
		$pathquery = !empty($query) && substr($query,0,1) != '?' ? $path.'?'.$query : $path.$query;
		
		if (empty($scheme))
		{
			throw new Exception('Protocole non défini !');
		}
		if (empty($host))
		{
			throw new Exception('URL non définie !');
		}
		
		return $scheme.'://'.$userpass.$hostport.$pathquery;
		
	}

	/**
	 * Retourne la liste des URL appelées
	 * @return string[]
	 */
	public function getHistory()
	{
		$history = array();
		foreach($this->history as $index => $request)
		{
			$history[$index] = $request->getEffectiveUrl();
		}
		return $history;
	}

	/**
	 * 
	 * @param int $pointer
	 * @return HTTPRequest
	 */
	public function getHistoryRequest($pointer)
	{
		return $this->history[$pointer];
	}
	
	/**
	 * Retourne le pointeur actuel de l'historique
	 * @return number
	 */
	public function getHistoryPointer()
	{
		return $this->historyPointer;
	}
	
	/**
	 * Retourne la taille de l'historique
	 * @return number
	 */
	public function getHistorySize()
	{
		
		return count($this->history);
	}
	
	/**
	 * Avancer/Reculer dans l'historique d'un interval donné
	 * @param integer $interval
	 *                    Décalage dans l'historique -1 pour revenir en arrière, 0 pour recharger la page, etc.
	 * @param string  $resend
	 *                    FALSE pour ne pas renvoyer les données de formulaire. TRUE par défaut
	 * @return Browser4
	 */
	public function historyGo($interval, $resend=TRUE)
	{
		$pointer = $this->historyPointer + $interval;
		return $this->historyGoTo($pointer);
	}

	/**
	 * Aller directement en un point de l'historique
	 * @param integer $pointer
	 *                    Point de l'historique où se rendre [0..historySize]
	 * @param string  $resend
	 *                    FALSE pour ne pas renvoyer les données de formulaire. TRUE par défaut
	 * @return Browser4
	 */
	public function historyGoTo($pointer,  $resend=TRUE)
	{
		if ($pointer < 0)
		{
			throw new Exception('Impossible de se rendre à l\'historique '.$pointer);
			return $this;
		}
		if ($pointer > count($this->history)-1)
		{
			throw new Exception('Impossible de se rendre à l\'historique '.$pointer);
			return $this;
		}
		
		$this->historyPointer = $pointer;
		$request = $this->history[$pointer];
		
		if (! $resend)
		{
			$request->clearPostData();			
		}
		
		$request->setUrl($request->getEffectiveUrl());
		
		return $this->_go($request);
	}

	/**
	 * Retour page précédente
	 * @return Browser4
	 */
	public function historyBack()
	{
		return $this->historyGo(-1);
	}
	
	/**
	 * Aller page suivante
	 * @return Browser4
	 */
	public function historyForward()
	{
		return $this->historyGo(+1);
	}
	
	/**
	 * Recharger la page
	 * @return Browser4
	 */
	public function historyReload()
	{
		return $this->historyGo(0);
	}
	
	/**
	 * Lance la requête et analyse le HTML récupéré
	 * @param HTTPRequest $request
	 * 
	 * @return $this
	 */
	protected function _go(HTTPRequest $request)
	{
		if (!empty($this->userAgent))
		{
			$request->addHeader('User-Agent', $this->userAgent);
		}
		
		$request->addHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8');
		$request->addHeader('Accept-Encoding', 'gzip, deflate');
		$request->addHeader('Accept-Language', 'fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4');
		$request->addHeader('Upgrade-Insecure-Requests', '1');
		
		$origin = isset($this->history[$this->historyPointer]) ? $this->history[$this->historyPointer] : NULL;
		if ($origin)
		{
			$request->addHeader('Referer', $origin->getEffectiveUrl());
		}
		
		if ($this->cookieFile)
		{
			$request->setCookieFile($this->cookieFile);
		}
		
		$this->_resetPage();
		$this->rawData   = $request->execute();
		$this->url       = $request->getEffectiveUrl();
		$this->http_code = $request->getHttpCode();
		$this->mime      = $request->getContentType();
		
		if ($request->isHTML())
		{
			$this->loadDOM();
		}

		$this->history[++$this->historyPointer] = $request;
		
		if ($this->exitIfError && $this->isError())
		{
			$data = $request->getPostData();
			fwrite(STDERR, '---- START OF ERROR INFO ----'. PHP_EOL);
			fwrite(STDERR, 'Error during browse to ' . $request->getEffectiveUrl() . PHP_EOL);
			fwrite(STDERR, 'Header and Body sent:'.PHP_EOL);
			fwrite(STDERR, $request->getHeaderSent().PHP_EOL);
			fwrite(STDERR, urldecode($request->getQueryString()).PHP_EOL.PHP_EOL);
			fwrite(STDERR, 'Header received:'.PHP_EOL);
			fwrite(STDERR, $request->getHeaderReceived().PHP_EOL.PHP_EOL);
			fwrite(STDERR, '---- END OF ERROR INFO ----'. PHP_EOL);
			file_put_contents('error.htm', $this->getRawData());
			exit(-1*$this->http_code);
		}
		
		return $this;
	}

	/**
	 * Vider les propriétés rawData, dom, form, formData
	 */
	protected function _resetPage()
	{
		$this->dom       = NULL;
		$this->rawData   = NULL;
		$this->formData  = array();
		$this->forms     = array();
	}
	
	/**
	 * Extraire l'ID ou le nom depuis la chaine indiquée
	 * Si la chaine $nameOrId commence par # : considéré comme un ID. Sinon considéré comme un nom
	 * 
	 * @param string  $nameOrId
	 * @param string& $id
	 * @param string& $name
	 * @return string 'id' ou 'name'
	 */
	protected static function _extractIdName($nameOrId, &$id, &$name)
	{
		$id   = NULL;
		$name = NULL;
		
		if ($nameOrId && strlen($nameOrId) > 1 && $nameOrId{0} = '#')
		{
			$id = substr($nameOrId, 1);
			return 'id';
		}
		
		$name = $nameOrId;
		return 'name';
	}
	
	/**
	 * Ajoute/Remplace une donnée POST par la valeur indiquée
	 * @param string $name
	 * @param mixed  $value
	 * 
	 * @return $this
	 */
	public function setFormData($name, $value)
	{
		$this->formData[$name] = $value;
		return $this;
	}

	/**
	 * Ajoute/Remplace une liste de données POST par les valeurs données
	 * @param array $data_list
	 *                  Association nom => valeur
	 * @param string $erase
	 *                  Si TRUE (par défaut), écrase les valeurs déjà présentes (série de setPostData())
	 * @see setPostData()
	 * 
	 * @return $this
	 */
	public function setFormDataList(array $data_list, $erase = TRUE)
	{
		foreach($data_list as $name => $value)
		{
			if ($erase || ! isset($this->formData[$name]))
			{
				$this->setFormData($name, $value);
			}
		}
		
		return $this;
	}
	
	/**
	 * Vider la liste des données de formulaire
	 */
	public function resetFormData()
	{
		$this->formData = array();
	}
	
	/**
	 * Envoyer les donnés formData selon l'URL, la méthode et l'enctype indiqué dans le formulaire
	 * @param string $formNameOrId
	 * @return $this
	 */
	public function submitForm($formNameOrId=NULL)
	{
		$form = $this->_getFormElement($formNameOrId);
		if ($form == NULL)
		{
			return $this;
		}
		$action  = $form->getAttribute('action');
		$method  = $form->getAttribute('method');
		$enctype = $form->getAttribute('enctype');
		return  $this->sendData($action, $method, $enctype);
	}
	
	/**
	 * Initialiser les données de formulaire avec le contenu du formulaire donné en paramètre
	 * @param string $formNameOrId
	 *                   Si ID, doit commencer par #
	 * @return $this
	 */
	public function initFormData($formNameOrId=NULL)
	{
		$form = $this->getForm($formNameOrId);
		$this->setFormDataList($form->getData());
		
		return $this;
	}
	
	/**
	 * Envoyer les données à l'URL indiquée selon la méthode et le format donné
	 * @param string $action   L'URL actuelle par défaut
	 * @param string $method   GET par défaut
	 * @param string $enctype  application/x-www-form-urlencoded par défaut
	 * 
	 * @return $this
	 */
	public function sendData($action=null, $method=null, $enctype=null)
	{
		if (empty($action))
		{
			$action = $this->url;
		}
		
		if (empty($method))
		{
			$method = HTTPRequest::METHOD_GET;
		}
		
		if (empty($enctype))
		{
			$enctype = HTTPRequest::MIME_URLENCODED;
		}
		
		$url = $this->buildUrl($action);
				
		$request = new HTTPRequest($url);
		$request->setMethod($method);
		$request->setEnctype($enctype);
		
		foreach($this->formData as $name => $value)
		{
			foreach((array)$value as $v)
			{
				$request->addPostData($name, $v);
			}
		}
		
		return $this->_go($request);
	}
	
	/**
	 * Récupère le formulaire indiqué par son nom ou son ID. Si vide, retourne le premier formulaire trouvé
	 * @param string $formNameOrId
	 *                  Si ID, doit commencer par #
	 * @return Form
	 */
	public function getForm($formNameOrId=NULL)
	{		

		$form = $this->_getFormElement($formNameOrId);

		if ($form == NULL)
		{
			return NULL;
		}
		
		$id    = $form->getAttribute('id');
		$name  = $form->getAttribute('name');
		$xpath = $form->getNodePath();
		
		$index = md5($xpath.$id.$name);
		
		if (isset($this->forms[$index]))
		{
			return $this->forms[$index];
		}
		
		$this->forms[$index] = new Form($form, $this);
		
		return $this->forms[$index];
		
	}
	
	/**
	 * 
	 * @param unknown $formNameOfId
	 * @return DOMElement
	 */
	protected function _getFormElement($formNameOrId=NULL)
	{
		if (empty($this->dom))
		{
			return NULL;
		}
		
		$form = NULL; /* @var DOMElement $form */
		
		
		$id = $name = NULL;
		self::_extractIdName($formNameOrId, $id, $name);
		
		if ($id)
		{
			$form = $this->dom->getElementById($id);
			if (empty($form))
			{
				return NULL;
			}
			return $form;
		}
		
		$query = '//form';
		
		if($name)
		{
			$query = '//form[@name="'.$name.'"]';
		}
		
		$domxpath = new DOMXPath($this->dom);
		$form_list = $domxpath->query($query);
		if ($form_list->length == 0)
		{
			return NULL;
		}
		
		return $form_list->item(0);
	}
	

	/**
	 * @return DOMDocument
	 */
	public function getDOM()
	{
		return $this->dom;
	}

	/**
	 * @return string
	 */
	public function getRawData()
	{
		return $this->rawData;
	}
	
	/**
	 * Chargement du DOM
	 */
	private function loadDOM()
	{
		if (empty($this->rawData))
		{
			return;
		}
		if (preg_match('`(<meta[^>]+http-equiv="?content-type"?[^>]+>)`', strtolower($this->rawData), $matche))
		{
			preg_match('`content="?charset=([A-Za-z0-9-]+)"?`', $matche[1], $m);
			$encoding = isset($m[1]) ? strtoupper(trim($m[1])) : NULL;
		}
		if (empty($encoding))
		{
			$encoding = mb_detect_encoding($this->rawData);
		}
		
		$this->dom = new DOMDocument('1.0', empty($encoding) ? 'UTF-8' : $encoding);
		@$this->dom->loadHTML($this->rawData);
	}
		
	
	/**
	 * Dernier code HTTP recu
	 * @return number
	 */
	public function getHttpCode()
	{
		return $this->http_code;
	}
	
	/**
	 * Dernier type MIME recu
	 * @return string
	 */
	public function getMimeType()
	{
		return $this->mime;
	}
	
	/**
	 * Est-ce que le document est un document DOM ? (HTML, ...)
	 * @return boolean
	 */
	public function isDOMDocument()
	{
		return !empty($this->dom);
	}
	

	/**
	 * La page est-elle chargée sans erreur ?
	 * @return boolean
	 */
	public function isLoaded()
	{
		return substr($this->http_code,0,1) == '2';
	}

	/**
	 * Une erreur (autre que "redirection") existe-t-elle ?
	 * @return boolean
	 */
	public function isError()
	{
		return substr($this->http_code,0,1) > 3;
	}
	
	
}