<?php
/**
 * Gestion d'une requête via cURL.
 *  
 * @author Francois RAOULT
 * 
 * @license GNU/LGPL
 *
 */

class HTTPRequest
{
	
	const METHOD_POST   = 'POST';
	const METHOD_GET    = 'GET';
	const METHOD_PUT    = 'PUT';
	const METHOD_PATCH  = 'PATCH';
	const METHOD_DELETE = 'DELETE';
	const METHOD_HEAD   = 'HEAD';
	
	const ENCODING_IDENTITY = 'identity';
	const ENCODING_DEFLATE  = 'deflate';
	const ENCODING_GZIP     = 'gzip';
	const ENCODING_ALL      = '';
	
	const MIME_URLENCODED   = 'application/x-www-form-urlencoded';
	const MIME_MULTIPART    = 'multipart/form-data';
	const MIME_TEXT         = 'text/plain';
	
	// Configuration
	
	/**
	 * Fichier contenant les cookies
	 * @var string
	 */
	protected $cookieFile = 'cookies.txt';
	
	// Requete
	
	/**
	 * En-tête HTTP à envoyer
	 * @var array
	 */
	protected $queryHeaders = array();
	
	/**
	 * Données à envoyer
	 * @var array
	 */
	protected $post    = array();
	
	/**
	 * Options cURL
	 * @var array
	 */
	protected $curlopt = array();
	
	/**
	 * URL demandée
	 * @var string
	 */
	protected $url     = NULL;
	
	/**
	 * Méthode à utiliser
	 * @var string
	 */
	protected $method  = NULL;
	
	/**
	 * Enctype à utiliser
	 * @var string
	 */
	protected $enctype = NULL;
	
	// Réponse

	/**
	 * Info cURL
	 * @var array
	 */
	protected $curlinfo = array();
	
	
	/**
	 * Les en-têtes reçus
	 * @var string
	 */
	protected $responseHeader = NULL;
	
	
	// CONSTRUCTEURS, DESTRUCTEURS
	// ---------------------------------------
	
	/**
	 * Constructeur en mode chainé
	 * @param string $url
	 *                   URL a appeler
	 * @param string $method
	 *                   Méthode HTTP
	 * @return HTTPRequest
	 */
	public static function create($url, $method=self::METHOD_GET)
	{
		return new self($url, $method);
	}
	
	/**
	 * Constructeur
	 * @param string $url
	 *                   URL a appeler
	 * @param string $method
	 *                   Méthode HTTP
	 */
	public function __construct($url, $method=self::METHOD_GET)
	{		
		$this->curlopt = array(
			CURLOPT_AUTOREFERER     => TRUE,
			CURLOPT_COOKIESESSION   => FALSE,
			CURLOPT_FOLLOWLOCATION  => TRUE,
			CURLOPT_RETURNTRANSFER  => TRUE,
			CURLOPT_SAFE_UPLOAD     => TRUE,
			CURLOPT_HEADER          => TRUE,
			CURLINFO_HEADER_OUT     => TRUE,
			CURLOPT_CONNECTTIMEOUT  => 30,
			CURLOPT_COOKIEFILE      => $this->cookieFile,
			CURLOPT_COOKIEJAR	    => $this->cookieFile,
			CURLOPT_SSL_VERIFYHOST  => FALSE,
			CURLOPT_SSL_VERIFYPEER  => FALSE
		);
		
		$this->setUrl($url);
		$this->setMethod($method);
	}
	
	/**
	 * Destructeur
	 */
	public function __destruct()
	{
	}
	
	
	// SETTERS / CONFIGURATION
	// ---------------------------------------
	
	/**
	 * Définir la méthode HTTP à utiliser
	 * @param string $method
	 * @throws Exception
	 * @return HTTPRequest
	 */
	public function setMethod($method)
	{
		$method = strtoupper($method);

		$this->method = $method;
		
		switch ($method)
		{
			case self::METHOD_GET:
				$this->setCurlOpt(CURLOPT_HTTPGET, TRUE);
				$this->setCurlOpt(CURLOPT_POST,    FALSE);
				$this->removeCurlOpt(CURLOPT_CUSTOMREQUEST);
				break;
			case self::METHOD_POST:
				$this->setCurlOpt(CURLOPT_HTTPGET, FALSE);
				$this->setCurlOpt(CURLOPT_POST,    TRUE);
				$this->removeCurlOpt(CURLOPT_CUSTOMREQUEST);
				break;
			case self::METHOD_HEAD:
			case self::METHOD_PUT:
			case self::METHOD_PATCH:
			case self::METHOD_DELETE:
				$this->setCurlOpt(CURLOPT_HTTPGET, FALSE);
				$this->setCurlOpt(CURLOPT_POST,    FALSE);
				$this->setCurlOpt(CURLOPT_CUSTOMREQUEST, $method);
				break;
			default:
				throw new Exception('Methode '.$method.' non reconnue');
		}
		return $this;
	}
	
	/**
	 * Fichier contenant les cookies
	 * @param string $file
	 */
	public function setCookieFile($file)
	{
		$this->setCurlOpt(CURLOPT_COOKIEFILE, $file);
		$this->setCurlOpt(CURLOPT_COOKIEJAR,  $file);
	}
	
	/**
	 * Définir l'URL à appler
	 * @param string $url
	 * @return HTTPRequest
	 */
	public function setUrl($url)
	{
		$this->url = $url;
		return $this;
	}
	
	/**
	 * Définir le type mime à utiliser : Url-encoded, plain-text ou Form-Data
	 * @param string $mime
	 * @return HTTPRequest
	 */
	public function setEnctype($mime)
	{
		$this->enctype = $mime;
		return $this;
	}
	
	/**
	 * Définir un port HTTP spécifique
	 * @param int $port
	 * @return HTTPRequest
	 */
	public function setPort($port)
	{
		$this->setCurlOpt(CURLOPT_PORT, $port);
		return $this;
	}
	
	/**
	 * Définir les données d'authentification HTTP
	 * @param string $user
	 * @param string $password
	 * @return HTTPRequest
	 */
	public function setHttpAuth($user, $password)
	{
		$this->setCurlOpt(CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		$this->setCurlOpt(CURLOPT_USERPWD, $user.':'.$password);
		return $this;
	}

	/**
	 * Définir les encodages acceptés : gzip, deflate ou entity ou tous (chaine vide)
	 * @param string $encoding
	 * @throws Exception
	 * @return HTTPRequest
	 */
	public function setAcceptEncoding($encoding)
	{
		switch ($encoding)
		{
			case self::ENCODING_ALL:
			case self::ENCODING_DEFLATE:
			case self::ENCODING_GZIP:
			case self::ENCODING_IDENTITY:
				$this->setCurlOpt(CURLOPT_ENCODING, $encoding);
				break;
			default:
				throw new Exception('Encodage '.$encoding.' non reconnu');
				
		}
		return $this;
	}
	
	/**
	 * Définir un User-Agent spécifique
	 * @param string $userAgent
	 * @return HTTPRequest
	 */
	public function setUserAgent($userAgent)
	{
		$this->setCurlOpt(CURLOPT_USERAGENT, $userAgent);
		return $this;
	}
	
	/**
	 * Définir l'interface à utiliser (nom ou IP ou nom d'hôte)
	 * @param string $interface
	 * @return HTTPRequest
	 */
	public function setInterface($interface)
	{
		$this->setCurlOpt(CURLOPT_INTERFACE, $interface);
		return $this;
	}
	
	/**
	 * Définir une option cURL quelconque
	 * @param string $opt_name
	 * @param mixed $value
	 */
	public function setCurlOpt($opt_name, $value)
	{
		$this->curlopt[$opt_name] = $value;
	}
	
	/**
	 * Définir une liste d'option cUrl à la façon de curl_setopt_array
	 * @param string $option_list
	 */	
	public function setCurlOptList($option_list)
	{
		foreach($option_list as $name => $value)
		{
			$this->curlopt[$name] = $value;
		}
	}
	
	/**
	 * Supprimer une option cURL
	 * @param string $opt_name
	 */
	public function removeCurlOpt($opt_name)
	{
		unset($this->curlopt[$opt_name]);
	}
	
	/**
	 * Ajouter une données en POST.
	 * @param string $key
	 * @param mixed  $value
	 * @return HTTPRequest
	 */
	public function setPostData($key, $value)
	{
		$this->post[$key] = $value;
		return $this;
	}
	
	/**
	 * Ajouter une valeur sur une clé déjà existante
	 * @param string $key
	 * @param string $value
	 * @return HTTPRequest
	 */
	public function addPostData($key, $value)
	{
		if (!isset($this->post[$key]) || ! is_scalar($value))
		{
			return $this->setPostData($key, $value);
		}
		
		if (! is_array($this->post[$key]))
		{
			$this->post[$key] = array(md5($this->post[$key]) => $this->post[$key]);
		}

		$this->post[$key][md5($value)] = $value;
		return $this;
	}
	
	/**
	 * Ajouter un fichier à envoyer
	 * @param string $key
	 * @param string $filepath
	 * @param string $mimetype
	 * @param string $postname
	 * @return HTTPRequest
	 */
	public function addPostFile($key, $filepath, $mimetype=NULL, $postname=NULL)
	{
		$this->setPostData($key, self::createPostFile($filepath, $mimetype, $postname));
		
		return $this;
	}
	
	
	/**
	 * Peupler l'ensemble des données à envoyer
	 * @param array $data
	 * @return HTTPRequest
	 */
	public function setPostDataList(array $data)
	{
		$this->post = $data;
		return $this;
	}
	
	/**
	 * Supprimer une valeur du Post
	 * @param string $key
	 * @param string $value [optionnel]
	 * @return HTTPRequest
	 */
	public function removePostData($key, $value=NULL)
	{
		if (empty($value))
		{
			unset($this->post[$key]);
		}
		
		if (isset($this->post[$key]) && !is_array($this->post[$key]))
		{
			unset($this->post[$key]);
		}
		
		unset($this->post[$key][md5($value)]);
		return $this;
	}
	
	
	/**
	 * Vider toutes les données à envoyer
	 * 
	 * @return HTTPRequest
	 */
	public function clearPostData()
	{
		$this->post = array();
		return $this;
	}
	
	/**
	 * Récupérer le tableau des données qui seront transmises
	 * @return array
	 */
	public function getPostData()
	{
		return $this->post;
	}

	/**
	 * Retourne les données sous forme de QueryString
	 * @return string
	 */
	public function getQueryString()
	{
		return $this->_getQueryString();
	}
	
	/**
	 * Ajouter un header. NULL pour supprimer. Si $value est un tableau, sera mis sous la forme "token=data, token=data, ..."
	 * @param string $key
	 * @param string $value
	 * @return HTTPRequest
	 */
	public function addHeader($key, $value)
	{
		if (is_array($value))
		{
			foreach ($value as $subkey=>$subvalue)
			{
				$value[$subkey] = $subkey.'='.$subvalue;
			}
			$value = implode(', ', $value);
		}
		$this->queryHeaders[$key] = $value;
		return $this;
	}
	
	// EXECUTER LA REQUETE
	// ---------------------------------------
	
	/**
	 * Exécuter la requête et retourne le résultat
	 * @return mixed
	 */
	public function execute()
	{
		// Initialise
		
		$curl = curl_init($this->url);
		curl_setopt_array($curl, $this->curlopt);
		$this->_setPostData($curl);
		
		// Execute
		
		$result = curl_exec($curl);
		
		// Récupère les infos et sépare le header du résultat
		
		$this->curlinfo       = curl_getinfo($curl);
		$headersize           = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$this->responseHeader = trim(substr($result, 0, $headersize));
		$result               = substr($result, $headersize+1);
		
		// Ferme la connexion (et enregistre les cookies)
		
		@curl_close($curl);
		
		// Retourne le corps renvoyé
		
		return $result;
	}

	

	/**
	 * Place les données POST dans cURL
	 * selon le format demandé ou nécessaire
	 */
	protected function _setPostData($curl)
	{
		// Pas de données
		
		if (empty($this->post))
		{
			return;
		}

		// Méthode GET : ajoute les données en tant que query_string
		
		if (strtoupper($this->method) == self::METHOD_GET)
		{
			$url = $this->url;
			if (strpos($url, '?') === FALSE)
			{
				$url = $url.'?'.$this->_getQueryString();
			}
			else
			{
				$url = $url.'&'.$this->_getQueryString();
			}
			curl_setopt($curl, CURLOPT_URL, $url);
			return;
		}
		
		// Autre : Format selon le Content-Type voulu et set POSTFIELDS et le header correspondant
		
		$type = $this->_detectMime($this->enctype);

		if ($type)
		{
			$this->queryHeaders['Content-Type'] = $type;
		}
		
		$postData = NULL;
		
		switch (strtolower($type))
		{
			
			case self::MIME_MULTIPART:
				$postData = $this->post;
				break;
				
			case self::MIME_URLENCODED:
				$postData = $this->_getQueryString();
				break;
				
			case self::MIME_TEXT:
				$postData = $this->_getQueryString();
				$postData = str_replace('%20', '+', $postData);
				break;
				
			default:
				$postData = $this->_getQueryString();
				break;
		}
		
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
		
	}
	

	// GETTER / RECUPERATION DU RESULTAT
	// ---------------------------------------
	
	/**
	 * Retourne l'en-tête HTTP reçu
	 * @return string
	 */
	public function getHeaderReceived()
	{
		return $this->responseHeader;
	}
	
	/**
	 * Retourne les infos cURL
	 * @return array
	 */
	public function getCurlInfo($option=NULL)
	{
		if ($option)
		{
			return isset($this->curlinfo[$option]) ? $this->curlinfo[$option] : NULL;
		}
		return $this->curlinfo;
	}
	
	/**
	 * Code HTTP reçu
	 * @return integer
	 */
	public function getHttpCode()
	{
		return $this->getCurlInfo('http_code');
	}

	/**
	 * URL effective après redirection
	 * @return string
	 */
	public function getEffectiveUrl()
	{
		return $this->getCurlInfo('url');
	}
	
	/**
	 * Nombre de redirections effectuées
	 * @return integer
	 */
	public function getRedirectCount()
	{
		return $this->getCurlInfo('redirect_count');
	}
	
	/**
	 * IP du serveur distant
	 * @return string
	 */
	public function getDistantIP()
	{
		return $this->getCurlInfo('primary_ip');
	}

	/**
	 * Port du serveur distant
	 * @return integer
	 */
	public function getDistantPort()
	{
		return $this->getCurlInfo('primary_port');
	}
	
	/**
	 * IP locale utilisée
	 * @return integer
	 */
	public function getLocalIP()
	{
		return $this->getCurlInfo('local_ip');
	}

	/**
	 * Port local utilisé
	 * @return integer
	 */
	public function getLocalPort()
	{
		return $this->getCurlInfo('local_port');
	}
	
	/**
	 * Taille de l'en-tête recu
	 * @return integer
	 */
	public function getHeaderSize()
	{
		return $this->getCurlInfo('header_size');
	}
	
	/**
	 * En-tête envoyé
	 * @return string
	 */
	public function getHeaderSent()
	{
		return $this->getCurlInfo('request_header');
	}

	/**
	 * Taille des données reçues, selon l'en-tête Content-Length indiqué par le serveur
	 * @return integer
	 */
	public function getContentLength()
	{
		return $this->getCurlInfo('download_content_length');
	}
	
	/**
	 * Type MIME retourné par le serveur, selon l'en-tête Content-Type
	 * @return string
	 */
	public function getContentType()
	{
		return $this->getCurlInfo('content_type');
	}
	
	/**
	 * Teste si le retour du serveur est du HTML (selon l'entête Content-Type)
	 * 
	 * @return boolean
	 */
	public function isHTML()
	{
		$contentType = $this->getContentType();
		$contentType = strtolower($contentType);
		
		if (strpos($contentType, 'text/htm') !== FALSE)
		{
			return TRUE;
		}
		if (strpos($contentType, 'application/xhtml') !== FALSE)
		{
			return TRUE;
		}
		return FALSE;
	}
	
	// OUTILS
	// ---------------------------------------
	
	/**
	 *
	 * @param string $filepath
	 * @param string $mimetype
	 * @param string $postname
	 * @return CURLFile
	 */
	public static function createPostFile($filepath, $mimetype=NULL, $postname=NULL)
	{
		if (! file_exists($filepath))
		{
			return NULL;
		}
		
		if (empty($mimetype))
		{
			$mimetype = mime_content_type($filepath);
		}
		if (empty($postname))
		{
			$postname = basename($filepath);
		}
		
		$file = new CURLFile($filepath);
		$file->setMimeType($mimetype);
		$file->setPostFilename($key);
		
		return $file;
	}
	
	/**
	 * Création de la chaine de requête depuis le tableau $this->post
	 * @return string Si la chaine n'a pas pu être créée (présence d'un CURLFile dans les données), retourne FALSE
	 */
	protected function _getQueryString()
	{
		if (empty($this->post))
		{
			return '';
		}
		
		$post_data = array();
		
		foreach($this->post as $key => $value)
		{
			if (is_object($value) && ($value instanceof CURLFile))
			{
				return FALSE;
			}
			foreach((array)$value as $v)
			{
				$post_data[] = urlencode($key).'='.urlencode($v);
			}
		}
		
		return implode('&', $post_data);
	}
	
	
	/**
	 * Detection automatique du type mime. Si un CURLFile est présent, retourne MULTIPART.
	 * Sinon retourne le type par défaut ou URLENCODED si non précisé
	 * @param string $default
	 * @return string
	 */
	protected function _detectMime($default=self::MIME_URLENCODED)
	{
		foreach($this->post as $key => $value)
		{
			if (is_object($value) && ($value instanceof CURLFile))
			{
				return self::MIME_MULTIPART;
			}
		}
		return $default;
	}
}